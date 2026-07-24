# Recovery document status

This document was reconstructed after the original architecture conversation became unavailable.

- “Confirmed” rules may be treated as authoritative.
- “Strongly inferred” and “Unknown” details require explicit approval before implementation.
- This document must not be silently changed when implementation differs from it.

# Ecommerce Version 1.0 — Architecture Recovery Report

No files were modified.

## 0. Evidence quality and recovery limits

The recovery used:

- Retained conversation context from this task.
- All migrations, models, enums, services, requests, controllers, seeders, tests, routes, and Blade files.
- Repository-wide architecture keyword searches.
- Git history and current diffs.
- Documentation and SQL export searches.

Important findings:

- Git contains only one commit: `2efc9d4 Initial Laravel project`.
- Almost all ecommerce implementation is currently uncommitted or untracked. Git cannot reconstruct the architectural decisions.
- [docs/database.md](C:\xampp\htdocs\my-ecommerce\docs\database.md) is empty.
- No relevant SQL exports or database dumps were found.
- Therefore, retained conversation context is the main evidence for frozen but unimplemented modules.
- Repository migrations and tests are the strongest evidence for implemented behavior, but some predate the final frozen architecture.

Confidence labels:

- **Confirmed** — explicitly retained as approved or directly enforced by migrations/tests.
- **Strongly inferred** — clearly indicated by final specification prompts or consistent implementation, but the complete approved specification is unavailable.
- **Unknown** — cannot be recovered safely.

---

# 1. Recovered module order

The approximately eleven frozen modules were:

1. Identity and Customers
2. Settings and Document Sequences
3. Catalog
4. Inventory
5. Shipping
6. Coupons
7. Carts
8. Orders and Snapshots
9. Payments and Payment Attempts
10. Wishlists
11. Notification Configuration

Additional cross-cutting subjects such as Taxes and Related Products were introduced later. Their database implementation exists, but their precise placement in the original eleven-module blueprint is not recoverable.

---

# 2. Module recovery

## Module 1 — Identity and Customers

**Overall confidence: Confirmed**

### Confirmed rules

- Administrators and customers share the `users` table.
- `account_type` values are `admin` and `customer`.
- No separate manual-customer account type exists.
- `has_account` distinguishes registered and manual customers:
  - Administrator: `admin`, `has_account = true`
  - Registered customer: `customer`, `has_account = true`
  - Manual customer: `customer`, `has_account = false`
- Guests do not receive a User record.
- Manual customers cannot authenticate unless `has_account = true`.
- Manual-customer upgrade preserves the same User ID.
- `email` and `password` are nullable for manual customers.
- Email remains globally unique.
- Phone is nullable and indexed, but not unique.
- `users.name` is the display name.
- Administrators and customers use separate session guards.
- Password reset eligibility requires:
  - `has_account = true`
  - email present
  - active account
- Active administrators and customers are enforced at authentication boundaries.
- Customer routes must never resolve administrator records.
- Customer order statistics include linked Completed orders only.
- Guest orders are never matched to customers by email.

### Confirmed tables and fields

`users`:

- `name`
- `first_name`, nullable
- `last_name`, nullable
- `email`, nullable, unique
- `phone`, nullable, indexed
- `password`, nullable
- `account_type VARCHAR(20)`
- `has_account`
- `is_active`
- `email_verified_at`
- `last_login_at`
- `remember_token`
- timestamps

`customer_addresses`:

- `user_id`
- first/last name
- company, nullable
- phone, nullable
- two address lines
- city
- state, nullable
- postal code, nullable
- `country_code CHAR(2)`
- `is_default`
- timestamps

### Relationships

- User has many CustomerAddresses.
- User has one filtered default address.
- User has many Orders.
- CustomerAddress belongs to User.
- CustomerAddress is deleted with its User.

### Validation/business rules

- CustomerService forces `account_type = customer`.
- Request input cannot change account type.
- Password writes use the model’s `hashed` cast exactly once.
- Blank phones normalize to `null`.
- Manual customers may lack email/password.
- Account-enabled customers require credentials.
- Passwords require at least eight characters, letters, numbers, and confirmation.
- Admin login rejects customers, manual administrators, and inactive administrators with a generic failure.
- Customer login rejects administrators, manual accounts, and inactive customers.

### Evidence

- [align_users_with_identity_architecture.php](C:\xampp\htdocs\my-ecommerce\database\migrations\2026_07_22_000003_align_users_with_identity_architecture.php)
- [CustomerAddress migration](C:\xampp\htdocs\my-ecommerce\database\migrations\2026_07_22_000002_create_customer_addresses_table.php)
- [User.php](C:\xampp\htdocs\my-ecommerce\app\Models\User.php)
- [CustomerService.php](C:\xampp\htdocs\my-ecommerce\app\Services\CustomerService.php)
- [config/auth.php](C:\xampp\htdocs\my-ecommerce\config\auth.php)
- Identity, Customer, Guard Isolation, and Admin Security tests.
- Retained approved Module 1 conversation.

---

## Module 2 — Settings and Document Sequences

**Overall confidence: Confirmed architecture, partially implemented**

### Confirmed rules

- Settings are mutable configuration.
- Database remains the source of truth.
- Settings are cached.
- Every successful update must invalidate the affected cache after persistence.
- Supported setting types include `json`.
- `settings.value` was approved as `LONGTEXT`.
- `document_sequences.sequence_key` is the approved identifier.
- Order numbers:
  - `ORD-YYYY-000001`
  - Year is informational.
  - Sequence never resets.
- Payment numbers:
  - `PAY-YYYY-000001`
  - Assigned to the payment obligation, not attempts.
- Inventory management is mandatory for inventory-bearing products.
- `manage_stock` and `allow_backorders` were explicitly removed from active configuration.
- Laravel cache/session/job tables are infrastructure, not ecommerce domain tables.

### Confirmed tables and fields

Implemented `settings` currently has:

- `id`
- `group`
- `key`
- `value TEXT`, nullable
- `type`
- timestamps
- unique `(group, key)`

Approved but unimplemented:

`document_sequences`:

- Exact full column list is not recoverable.
- The identifier column is definitely `sequence_key`.
- It owns non-resetting human-readable document sequences.

### Relationships

- No domain foreign keys are evident for settings.
- Document sequences are shared infrastructure consumed by number-generator services, not owned by Orders or Payments.

### Business rules

- Setting lookup uses `group.key`.
- Current helper supports boolean, integer, decimal/number, JSON, and text-like values.
- Single configured currency and store timezone are global settings.

### Evidence

- [settings migration](C:\xampp\htdocs\my-ecommerce\database\migrations\2026_07_16_192710_create_settings_table.php)
- [SettingHelper.php](C:\xampp\htdocs\my-ecommerce\app\Helpers\SettingHelper.php)
- [SettingSeeder.php](C:\xampp\htdocs\my-ecommerce\database\seeders\SettingSeeder.php)
- `OrderNumberGenerator` implementation.
- Retained approved Module 2 conversation.

### Conflict

- Repository uses `TEXT`, while frozen architecture approved `LONGTEXT`.
- `document_sequences` is absent.
- `manage_stock` and `allow_backorders` remain seeded despite being removed from the authoritative configuration.

---

## Module 3 — Catalog

**Overall confidence: Confirmed and substantially implemented**

### Confirmed rules

- Catalog owns product identity, classification, translations, configuration, and imagery.
- Catalog does not own inventory quantities or low-stock policy.
- Product types:
  - `simple`
  - `configurable`
  - `bundle`
- A configurable variant is represented as a Simple Product with `configurable_id`.
- Configurable parents and bundle parents do not directly own inventory.
- Categories use an unlimited recursive hierarchy.
- Root category technical `level = 0`.
- Child level equals parent level plus one.
- Category services maintain levels and prevent cycles.
- Parent-category deletion is restricted while children exist.
- Configurable-parent deletion is restricted while variants exist.
- Variants cannot become orphan standalone products through deletion.
- Attribute option codes are machine-readable:
  - lowercase
  - normalized
  - ASCII-safe
  - unique per Attribute
  - immutable after business use
- New option codes derive from the English label with deterministic collision and empty-label fallback behavior.
- Customer-facing translated entities must have every required storefront locale before activation.
- Physical deletion is exceptional; deactivation is normal.
- Product deletion is blocked by transactional or protected relationships.
- Related products are directional, explicit, and ordered. There is no automatic fallback.

### Confirmed tables

- `attributes`
- `attribute_translations`
- `attribute_options`
- `attribute_translation_options`
- `categories`
- `category_translations`
- `category_filterable_attributes`
- `products`
- `product_translations`
- `product_categories`
- `product_images`
- `product_attribute_values`
- `product_super_attributes`
- `product_super_attribute_options`
- `bundle_options`
- `bundle_option_translations`
- `bundle_option_items`
- `product_related_products`
- `taxes` and Product tax fields were added later

### Confirmed relationships

- Attribute has translations, options, product values, configurable usages, and filterable categories.
- Category belongs to optional parent and has children.
- Product belongs to many Categories.
- Product has translations, images, attribute values, inventory, and movements.
- Configurable parent has many variants.
- Bundle has options; options have translated labels and product items.
- Product has directional related products.
- Product optionally belongs to Tax.

### Validation/business rules

- Attribute code/type are protected after creation.
- Referenced AttributeOptions cannot change code or be deleted.
- ProductService does not create or mutate inventory.
- Initial configurable creation and later variant generation prevent duplicate combinations.
- Add Variant uses all current options for assigned super attributes, not only previously used options.
- Products with Orders, InventoryMovements, variants, or bundle dependencies cannot be deleted.
- Unused products and unused leaf Categories may be deleted transactionally.
- Stored images are removed only after successful persistence.

### Evidence

- Catalog migrations dated July 16–18 and alignment migrations dated July 22.
- [Product.php](C:\xampp\htdocs\my-ecommerce\app\Models\Product.php)
- [ProductService.php](C:\xampp\htdocs\my-ecommerce\app\Services\ProductService.php)
- [AttributeService.php](C:\xampp\htdocs\my-ecommerce\app\Services\AttributeService.php)
- [CategoryService.php](C:\xampp\htdocs\my-ecommerce\app\Services\CategoryService.php)
- Full Catalog test directory.
- Retained approved Module 3 conversation.

---

## Module 4 — Inventory

**Overall confidence: Confirmed and implemented**

### Confirmed rules

- `ProductInventory` is the current-state projection.
- `InventoryMovement` is the immutable ledger.
- InventoryService is the only component permitted to modify inventory, except documented migrations/repairs.
- No stock reservation exists.
- No `reserved_quantity` exists in the authoritative schema.
- `ProductInventory` owns:
  - quantity
  - average cost
  - low-stock threshold
- Available quantity equals on-hand quantity.
- Inventory-bearing entities are standalone Simple Products and configurable variants.
- Configurable and bundle parents are ineligible.
- Opening stock is allowed only before any InventoryMovement exists.
- Opening quantity and cost must both be greater than zero.
- No opening intention means no opening movement.
- Receipts recalculate weighted-average cost.
- Outbound Sale uses the locked average cost.
- Sale movements and `OrderItem.unit_cost` use exactly the same cost.
- Returns use the original Sale movement’s cost and do not recalculate weighted average.
- No operation may make inventory negative.
- Requirements are validated completely before writes.
- Rows are locked in ascending product-ID order.
- Processing deducts stock exactly once.
- Eligible cancellation and Delivery Failed restore stock exactly once.
- Every new movement stores before and after quantities.
- Ledger records are append-only.

### Confirmed tables and fields

`product_inventories`:

- unique `product_id`
- `quantity DECIMAL(15,4)`
- `average_cost DECIMAL(15,4)`
- `low_stock_alert DECIMAL(15,4)`, nullable
- timestamps
- no authoritative `reserved_quantity`

`inventory_movements`:

- `product_id`
- `type`
- signed `quantity`
- `quantity_before`
- `quantity_after`
- `unit_cost`
- `total_cost`
- nullable `reference_type` and `reference_id`
- notes
- nullable creator
- timestamps

Movement types implemented:

- opening
- receipt
- adjustment
- stock_count
- sale
- return

### Indexes

- `(product_id, created_at)`
- `(reference_type, reference_id)`
- `created_at`
- `(type, created_at)`
- `(product_id, type, created_at)`

### Evidence

- [Inventory alignment migration](C:\xampp\htdocs\my-ecommerce\database\migrations\2026_07_23_000001_align_inventory_architecture.php)
- [InventoryService.php](C:\xampp\htdocs\my-ecommerce\app\Services\InventoryService.php)
- [InventoryMovement.php](C:\xampp\htdocs\my-ecommerce\app\Models\InventoryMovement.php)
- [InventoryServiceTest.php](C:\xampp\htdocs\my-ecommerce\tests\Feature\InventoryServiceTest.php)
- Order lifecycle and cost-migration tests.
- Retained approved Module 4 and Stage 3/3B conversations.

---

## Module 5 — Shipping

**Overall confidence: Confirmed rules, schema details incomplete, unimplemented**

### Confirmed rules

- Shipping methods support:
  - Home Delivery
  - Store Pickup
- Shipping zones, zone areas, rates, and pickup locations are required.
- Delivery methods:
  - may own ShippingRates
  - must not use PickupLocations
- Pickup methods:
  - may use PickupLocations
  - must not own ShippingRates
- All active PickupLocations are available to all active pickup methods in Version 1.
- No pickup-method/location junction table in Version 1.
- Zone matching precedence:
  1. Country + Governorate + City
  2. Country + Governorate
  3. Country
- Matching at the same specificity must be unique across active zones.
- Country is required.
- City cannot exist without Governorate.
- Governorate may be null only when City is also null.
- Rate minimum is inclusive.
- Rate maximum is exclusive.
- Null maximum means no upper bound.
- Shipping-rate subtotal is merchandise after line/product discounts but before order coupon, shipping, and tax.
- Shipping Method and Pickup Location translations are required.
- Every active translated customer-facing entity must contain all required storefront locales.
- Delivery and Pickup Orders use immutable shipping snapshots.

### Likely tables

Confirmed by retained context:

- `shipping_methods`
- `shipping_method_translations`
- `shipping_zones`
- `shipping_zone_areas`
- `shipping_rates`
- `pickup_locations`
- `pickup_location_translations`

Exact complete fields and delete rules cannot be recovered.

### Relationships

- ShippingMethod has many translations.
- Delivery ShippingMethod may have many rates.
- ShippingZone has many areas and rates.
- PickupLocation has many translations.
- Version 1 has no ShippingMethod–PickupLocation pivot.

### Evidence

- Retained approved Module 5 conversation.
- No repository migration, model, service, request, controller, or test exists.

---

## Module 6 — Coupons

**Overall confidence: Confirmed rules, schema details incomplete, unimplemented**

### Confirmed rules

- One coupon per Order.
- Coupon codes are manually created by administrators.
- Codes are trimmed and stored canonically in uppercase.
- Matching is case-insensitive.
- CouponUsage is created atomically during Order creation.
- The Coupon row is locked before usage counts are read.
- Effective counts derive from authoritative Usage and Release records, never cached counters.
- `CouponUsage` is append-only and serves as the Order coupon snapshot.
- No separate Order coupon snapshot table exists.
- Unique `coupon_usages.order_id` enforces one coupon per Order.
- Eligible subtotal is merchandise after product pricing/discounts, before coupon, shipping, and tax.
- Guest coupon use is allowed only when:
  - not first-order-only
  - per-customer limit is null
- First-order coupons require a registered customer with no Completed Orders at validation time.
- First-order validation also rejects an active unreleased first-order usage.
- Cancellation may release eligibility through immutable `CouponUsageRelease`.
- At most one release per usage.
- Completed Orders never release usage.
- CouponUsageRelease must belong to the Order currently transitioning to Cancelled.
- Validity uses one captured store-timezone instant:
  - `starts_at <= now`
  - `now < ends_at`
- Persistent timestamps are UTC.
- Coupon codes become immutable after first usage.
- Operational replacement should create a new Coupon and deactivate the old one.

### Confirmed tables

- `coupons`
- `coupon_usages`
- `coupon_usage_releases`

Exact complete Coupon fields, target/eligibility pivots, and whether `eligible_subtotal` was finalized cannot be proven.

### Relationships

- Coupon has many usages.
- Order has at most one CouponUsage.
- CouponUsage has at most one CouponUsageRelease.
- User/customer relationship is nullable where guest use is permitted.

### Evidence

- Retained approved Module 6 conversation.
- No repository implementation exists.

---

## Module 7 — Carts

**Overall confidence: Confirmed core rules, exact schema incomplete, unimplemented**

### Known authoritative rules

- Cart is a persistent database aggregate.
- Database persistence is the source of truth.
- Exactly one ownership mechanism:
  - `user_id`
  - or `guest_token_hash`
- Never both and never neither.
- Guest token:
  - generated cryptographically
  - at least 256 bits of entropy
  - raw value never persisted
  - persisted value is SHA-256
- Customer carts persist after logout but are not anonymously exposed.
- Carts are mutable, ephemeral working state.
- Cart items store identifiers and quantities, not Eloquent models.
- Prices are recalculated from current Catalog data.
- Orders alone store historical pricing snapshots.
- Cart stock validation checks current availability but does not reserve stock.
- Cart presence never guarantees stock at Checkout.
- `expires_at` derives from `last_activity_at + configured lifetime`.
- Only meaningful mutations extend expiry.
- Default configured lifetime is 30 days.
- Successful Checkout clears the Cart only after all Order records, snapshots, CouponUsage, and initial Payment are successfully created.
- Cart Coupons are provisional. CouponUsage is created only during successful Order creation.
- `configuration_hash` is a deterministic derived merge key.
- Bundle configuration remains authoritative in `cart_item_bundle_items`.
- Invalid bundle configuration is automatically removed during validation with a clear customer message.
- Zero or negative quantity removes the CartItem.
- Guest Cart always merges into the existing Customer Cart.
- Customer Cart identity never changes.
- Guest Cart is deleted after successful merge.
- Browser guest token is invalidated; a future anonymous session receives a new token.
- Cart ownership may transition only through approved guest-to-customer merge.
- Customer carts cannot be reassigned or converted back to guest carts.

### Confirmed/remembered tables

- `carts`
- `cart_items`
- `cart_item_bundle_items`
- Additional option/configuration or provisional coupon structure may exist in the frozen blueprint, but exact table names and fields are not recoverable safely.

### Relationships

- User has at most one active Customer Cart under Version 1 behavior.
- Cart has many CartItems.
- CartItem belongs to Product through a live reference.
- Bundle CartItem configuration has child configuration rows.
- Guest Cart uses token-hash ownership rather than User ownership.

### Current repository state

- No Cart migration, model, service, controller, request, route, or tests.
- [cart.blade.php](C:\xampp\htdocs\my-ecommerce\resources\views\shop\pages\cart.blade.php) exists but is empty.
- Product Details currently has only a placeholder Add to Cart control.
- Storefront navbar still contains static template cart links.

### Evidence

- Retained frozen Module 7 decisions.
- Current repository inspection.
- No implementation evidence.

---

## Module 8 — Orders and Snapshots

**Overall confidence: Frozen rules confirmed; repository implements an earlier version**

### Confirmed frozen rules

- Order numbers use the global non-resetting sequence.
- Orders are historical sources of truth through immutable snapshots.
- Order lifecycle:
  - Pending
  - Processing
  - Completed
  - Cancelled
- Fulfillment lifecycle:
  - Unfulfilled
  - Out for Delivery
  - Fulfilled
  - Delivery Failed
- Store Pickup uses `Unfulfilled → Fulfilled`.
- Every Order has exactly one `order_shipping` row.
- Delivery and Pickup shipping snapshots have different required snapshot fields.
- Delivery Orders have billing and shipping OrderAddresses.
- Pickup Orders have only billing OrderAddress; destination exists in OrderShipping.
- Order addresses are immutable in Version 1.
- Product item snapshots are not derived later from current Product data.
- `order_item_options` stores normalized option snapshots:
  - attribute code/name
  - option code/label
- Bundle parents are descriptive and monetary-zero.
- Bundle children own monetary values and inventory relevance.
- Coupon discount allocation is proportional across monetary leaf items.
- Bundle parents receive zero allocation.
- Order source stores a nullable FK plus immutable code/name snapshots.
- `customer_email` is nullable; Checkout policy determines contact requirements.
- Mutable `admin_notes` was rejected.
- Append-only OrderNotes may be introduced only through future approval.
- Status History is authoritative for transitions.
- Only milestone timestamps remain on Order.
- OrderItem snapshot types:
  - simple
  - variant
  - bundle
  - bundle_item
- `OrderItem.unit_cost`:
  - null at Checkout
  - written exactly once during Processing
  - equals the exact Sale movement average cost
  - immutable after being set

### Confirmed lifecycle rules

- Create: Pending / Pending / Unfulfilled.
- Process:
  - Pending only
  - method prepayment policy enforced
  - inventory deducted once
  - cost snapshotted
- Out for Delivery:
  - Processing + Unfulfilled
- Fulfill delivery:
  - Processing + Out for Delivery
  - auto-complete only when Paid
- Delivery Failed:
  - Processing + Out for Delivery
  - fulfillment becomes Delivery Failed
  - Order becomes Cancelled
  - inventory restored once
  - Paid status remains Paid
  - requires future refund warning
- Normal Cancel:
  - Pending
  - or Processing + Unfulfilled
  - not after dispatch/fulfillment/completion
- Automatic Completion:
  - Processing + Paid + Fulfilled
  - works whether payment or fulfillment happens last

### Current implemented tables

- `orders`
- `order_items`
- `order_addresses`
- `order_payments`
- `order_status_history`

### Implemented relationships

- Order belongs to nullable User.
- Order has many Items, Addresses, Payments, and StatusHistory.
- OrderItem supports parent/children.
- OrderAddress supports billing/shipping filtered relationships.
- OrderPayment belongs to Order.
- Status history belongs to Order and nullable acting User.

### Evidence

- Order migrations dated July 20–23.
- `OrderService`, `OrderStatusService`, `OrderCompletionService`.
- OrderCreation, OrderLifecycle, and OrderCostMigration tests.
- Retained frozen Module 8 conversation.

### Conflicts with frozen Module 8

Repository lacks:

- `order_shipping`
- `order_item_options`
- order-source FK/code/name snapshots
- complete shipping snapshots
- coupon usage integration

Repository still has:

- non-null `customer_email`
- mutable `admin_notes`

The existing Order schema therefore represents an earlier approved implementation, not the final frozen Module 8 blueprint.

---

## Module 9 — Payments and Payment Attempts

**Overall confidence: Core rules confirmed; exact authoritative schema incomplete; repository is an older design**

### Confirmed frozen rules

- One Order owns exactly one payment obligation.
- The payment obligation is represented by one `OrderPayment`.
- Payment attempts are separate immutable records.
- Every retry creates a new PaymentAttempt.
- Failed attempts are never edited.
- Terminal attempts are immutable.
- Payment numbers:
  - `PAY-YYYY-000001`
  - belong to OrderPayment only
- Payment attempts do not need human-readable payment numbers.
- Attempts use `attempt_number`.
- Provider-specific data belongs in PaymentAttempt payload/metadata, not OrderPayment columns.
- Orders retain an aggregate payment projection.
- Payment method code/name data required historically is snapshotted.
- Lifecycle services must not branch on hardcoded method names.
- PaymentMethod configuration owns `requires_payment_before_processing`.
- Payment actions are forbidden for Cancelled and Completed Orders.
- Paid Delivery Failed Orders remain Paid and await a future Refund module.
- No automatic refund.
- Notifications are post-commit future behavior and must not roll back payment transactions.

### Approved Version 1 methods

- `cash_on_delivery`
- `bank_transfer`
- `whish_manual_transfer`
- `whish_api`
- `omt_manual_transfer`
- `omt_api`

Manual and API methods can be active simultaneously.

### Current repository

`payment_methods` currently contains the right foundational fields:

- id
- code
- name
- active flag
- prepayment requirement
- sort order
- timestamps

But the seeder currently creates only:

- `cash_on_delivery`
- `online_card`

Current `order_payments` acts as a mutable attempt table:

- multiple rows per Order
- status
- amount
- transaction reference
- failure message
- paid/failed timestamps

`PaymentStatusService::retry()` creates a new `order_payments` row.

### Evidence

- [PaymentStatusService.php](C:\xampp\htdocs\my-ecommerce\app\Services\PaymentStatusService.php)
- [payment methods migration](C:\xampp\htdocs\my-ecommerce\database\migrations\2026_07_21_000001_create_payment_methods_table.php)
- [OrderPayment migration](C:\xampp\htdocs\my-ecommerce\database\migrations\2026_07_20_000004_create_order_payments_table.php)
- Order lifecycle tests.
- Retained Module 9 and payment architecture decisions.

### Conflict

Frozen Module 9 requires one OrderPayment plus immutable PaymentAttempts. The repository instead uses multiple mutable `order_payments` rows as attempts and has no `payment_attempts` table or payment number.

---

## Module 10 — Wishlists

**Overall confidence: Strongly inferred core rules, exact schema unknown, unimplemented**

### Recovered rules

- Wishlists are mutable convenience data, not transactional history.
- Registered customers only; guests cannot own wishlists.
- One Wishlist per Customer in Version 1.
- Duplicate WishlistItems are prohibited.
- WishlistItems store live Product references, not snapshots.
- Product names, prices, stock, and availability reflect current Catalog state.
- Disabled/out-of-stock products may remain visible according to current availability rules, but cannot bypass Cart validation.
- Deleted Products remove dependent WishlistItems.
- Add to Cart does not automatically delete the WishlistItem.
- Wishlist operations never mutate Orders, Inventory, Payments, Coupons, or Shipping.
- Default ordering is newest first.
- Version 1 does not include multiple lists, folders, sharing, notes, priorities, gift registries, or alerts.

### Likely tables

- `wishlists`
- `wishlist_items`

Exact columns, uniqueness definitions, and delete constraints are not recoverable from repository evidence.

### Relationships

- Customer has at most one Wishlist.
- Wishlist has many WishlistItems.
- WishlistItem belongs to exactly one Product through a live reference.

### Evidence

- Retained final Module 10 specification request and frozen baseline.
- No repository implementation.

---

## Module 11 — Notification Configuration

**Overall confidence: Strongly inferred core rules, exact schema and seeded codes unknown, unimplemented**

### Recovered rules

- Module 11 configures notification behavior only.
- It does not send notifications.
- It does not contain delivery history.
- Future delivery history belongs to `notification_deliveries`.
- Notification Events are application-defined.
- Administrators cannot create arbitrary Events.
- Event codes are immutable.
- Notification Channels are application-defined.
- Channel codes are immutable.
- Notification Rules are mutable configuration.
- Each Rule references exactly:
  - one Event
  - one Audience
  - one Channel
- Missing Rules behave as disabled.
- Rule changes affect only future notifications.
- Notification failure must never roll back a business transaction.
- Dispatch occurs after successful business commit.
- Configuration may be cached, with correct invalidation after updates.
- Version 1 was expected to cover administrator and customer audiences and localized behavior.

### Likely tables

- `notification_events`
- `notification_channels`
- `notification_audiences`
- `notification_rules`

The exact translation/template structure and seeded event/channel/audience codes cannot be recovered.

### Evidence

- Retained Module 11 final-specification prompt and architecture baseline.
- No migrations, models, services, or tests in the repository.

---

# 3. Exact retained decisions not fully reflected in code

These decisions are remembered from the missing architecture discussion even where implementation is absent:

1. Normalize by default; never optimize for fewer tables.
2. Every business fact has exactly one owner.
3. Configuration is mutable; transactional history is immutable.
4. Orders own historical snapshots.
5. Carts and Wishlists are mutable convenience/working state.
6. Services own business transactions and lifecycle rules.
7. Controllers coordinate only.
8. Database constraints complement service validation.
9. Controlled application statuses use `VARCHAR` plus PHP backed enums.
10. Lookup tables represent configurable business data.
11. SQL ENUM is avoided unless a classification is genuinely invariant.
12. Persistent timestamps use UTC; business-time validation uses configured store timezone.
13. Cache never becomes the source of truth and must be invalidated only after successful persistence.
14. Post-commit notification failure cannot roll back commerce actions.
15. Physical deletion of protected transactional history is prohibited.
16. Orders and payment snapshots remain valid after Catalog or configuration changes.
17. Inventory is deducted only at Pending → Processing.
18. No reservation or backorder system exists.
19. Coupon usage is created during Order creation, not Cart selection.
20. Cart prices remain live and are recalculated.
21. Guest Checkout does not create a User.
22. Manual customer upgrade retains the same User and Order history.
23. Storefront supports English and Arabic; admin is English.
24. Store operates with one configured currency.
25. Home Delivery and Store Pickup are both required.
26. Guest Checkout is configurable through Settings.
27. One Coupon per Order.
28. Order sources are configurable.
29. Wishlist is registered-customer-only.
30. Cart persists beyond logout and expires after configured inactivity.
31. Inventory movement reference types were recommended to use a morph map eventually, but that migration was explicitly deferred.
32. Taxes were later added with:
    - `DECIMAL(8,4)` rate
    - unique name
    - active status
    - nullable Product tax with `nullOnDelete`
    - default/product-specific tax resolution
33. Related Products are directional and manually selected.

---

# 4. Unresolved details that cannot be safely recovered

The following must not be guessed:

1. Full `document_sequences` column list and locking/version strategy.
2. Full Shipping table columns, indexes, and delete policies.
3. Full Coupon table eligibility structure and whether product/category targeting was approved.
4. Whether `coupon_usages.eligible_subtotal` was finally mandatory or merely recommended.
5. Exact Cart schema:
   - all Cart fields
   - CartItem configuration columns
   - exact unique keys
   - provisional coupon persistence
   - expiration setting key
6. Exact merge-conflict rules when Customer and Guest carts contain the same configurable or bundle configuration.
7. Complete frozen Order columns and indexes.
8. Exact `order_shipping` schema.
9. Exact Order tax/currency snapshot fields beyond currently remembered totals.
10. Full PaymentMethod provider/configuration relationships.
11. Full OrderPayment and PaymentAttempt column specifications.
12. Approved payment-provider table names and provider ownership.
13. Callback idempotency and reconciliation column names.
14. Exact Wishlist fields and database uniqueness constraints.
15. Whether disabled products remain stored in Wishlists or are removed.
16. Exact Notification Event, Channel, Audience, and Rule columns.
17. Seeded notification event codes, categories, channels, audiences, and defaults.
18. Whether localized templates belong in Version 1 configuration or only a future templates module.
19. Reviews architecture. Reviews were mentioned in recovery search scope but do not appear among the frozen eleven modules or current database.
20. Formal architectural ownership of the later Taxes and Related Products additions.

---

# 5. Repository conflicts with remembered frozen architecture

## Major conflicts

1. **Cart architecture**
   - Frozen: persistent database Cart aggregate.
   - Recent requested implementation: session-only Cart.
   - Repository: no Cart implementation.
   - A session-only Cart would violate the frozen architecture.

2. **Payment aggregate**
   - Frozen: one OrderPayment plus immutable PaymentAttempts.
   - Repository: multiple mutable `order_payments` rows acting as attempts.

3. **Order snapshots**
   - Frozen: mandatory one-to-one `order_shipping`, normalized `order_item_options`, source snapshots.
   - Repository: these tables/fields are absent.

4. **Order fields**
   - Frozen: nullable `customer_email`, no mutable `admin_notes`.
   - Repository: customer email is required and `admin_notes` exists.

## Moderate conflicts

5. **Settings storage**
   - Frozen: `LONGTEXT`.
   - Repository: `TEXT`.

6. **Document sequences**
   - Frozen: shared non-resetting sequence aggregate.
   - Repository: absent; Order number generation currently derives the next number from existing Orders.

7. **Obsolete inventory settings**
   - Frozen: no `manage_stock` or `allow_backorders`.
   - Repository: both are still seeded.

8. **Payment methods**
   - Frozen: six approved Lebanese payment methods.
   - Repository: only COD and `online_card`.

## Aligned corrections already present

- Reserved inventory was removed.
- Inventory indexes were added.
- Category/configurable parent deletion became restrictive.
- Attribute option codes were added and protected.
- OrderItem processing costs are reconstructed only from Sale movements.
- Separate admin/customer guards exist.
- Manual-customer nullable credentials exist.

---

# 6. Safe continuation recommendation

## Safe to continue with limited changes

### Module 1 — Identity and Customers

The implementation is strongly aligned and well-tested. Continue only with bug fixes or already-approved UI work.

### Module 3 — Catalog

Substantially aligned and tested. Continue with Catalog features only when they do not change frozen ownership.

### Module 4 — Inventory

The current implementation closely matches the recovered frozen rules. Continue with careful regression coverage.

## Requires a small authoritative correction document first

### Module 2 — Settings and Document Sequences

Recover or restate the exact `document_sequences` schema and correct the Settings mismatches before relying on number generation for new modules.

## Must be reconstructed before implementation

### Module 5 — Shipping
### Module 6 — Coupons
### Module 7 — Carts
### Module 10 — Wishlists
### Module 11 — Notification Configuration

Their business rules are substantially recoverable, but their exact schemas are not.

## Must be reconciled before further extension

### Module 8 — Orders
### Module 9 — Payments

Working implementations exist, but they predate significant frozen structural decisions. New Checkout, Cart-to-Order conversion, payment-provider, refund, shipping, or coupon work should not build on the current schema until Modules 8 and 9 are restated authoritatively.

## Recommended reconstruction order

1. Persist this recovered report outside the chat.
2. Reconstruct Module 2 document sequences.
3. Reconstruct Module 7 Cart schema before implementing Cart.
4. Reconstruct Module 8 final Order schema.
5. Reconstruct Module 9 Payment obligation/attempt schema.
6. Reconstruct Shipping and Coupons before Checkout.
7. Reconstruct Wishlists and Notifications afterward.
8. Commit the current repository state in small, labeled commits so future recovery can rely on Git.

The immediate Cart implementation should remain paused until the authoritative Module 7 tables and constraints are restored.

---

# Approved authoritative amendment — Module 7 Cart

The previously unresolved Module 7 schema and business decisions have now been
explicitly approved. The authoritative specification is:

[`docs/ecommerce-v1-module-7-cart.md`](ecommerce-v1-module-7-cart.md)

This amendment supersedes only statements in this recovery report that describe
Module 7 as unresolved or paused. It does not silently alter any other recovered
module, confidence label, or uncertainty.

---

# Approved authoritative amendment — Bundle Product retirement

Following a project-wide dependency and implementation review, Bundle Product
support was intentionally and permanently removed from Ecommerce Version 1.0.
Version 1 supports only Simple and Configurable Products.

Bundle administration and schema foundations existed, but end-to-end storefront
pricing, Cart handling, Checkout conversion, and purchasing behavior were never
implemented. The Bundle Catalog and Cart tables were therefore retired through a
new forward migration. Previously applied migrations remain preserved as
historical migration records.

Historical `order_items.product_type` snapshot values such as `bundle` and
`bundle_item` remain valid immutable history and MUST NOT be rewritten. Order
lifecycle behavior continues to rely on `is_inventory_item`, parent relationships,
and OrderItem snapshots rather than live Bundle models.

This amendment supersedes earlier recovered statements that describe Bundle
Products as an active or future Version 1 capability. It does not redesign
Simple Products, Configurable Products, generic Order parent-child snapshots, or
historical Order records.
