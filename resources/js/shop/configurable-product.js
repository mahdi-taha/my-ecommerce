export function initializeConfigurableProducts() {
    document.querySelectorAll('[data-configurable-product-form]').forEach((form) => {
        const variantData = form.querySelector('[data-configurable-variants]');
        const variants = Object.values(JSON.parse(variantData?.textContent || '{}'));
        const attributeGroups = Array.from(form.querySelectorAll('[data-configurable-attribute]'));
        const quantity = form.querySelector('input[name="quantity"]');
        const quantityButtons = form.querySelectorAll('.quantity button');
        const submit = form.querySelector('button[type="submit"]');
        const priceContainer = document.querySelector('[data-product-price]');
        const initialPriceNodes = Array.from(priceContainer?.childNodes ?? [])
            .map((node) => node.cloneNode(true));
        const availability = document.querySelector('[data-product-availability] .badge');
        const availabilityIcon = availability?.querySelector('i');
        const availabilityLabel = availability?.querySelector('[data-availability-label]');
        const sku = document.querySelector('[data-variant-sku]');
        const skuValue = sku?.querySelector('span');
        const mainImages = document.querySelectorAll('[data-product-main-image]');
        const imagePlaceholders = document.querySelectorAll('[data-product-image-placeholder]');
        const parentImage = mainImages[0]?.getAttribute('src') ?? null;

        const selectedValue = (group) => {
            const select = group.querySelector('[data-configurable-select]');

            return select?.value
                ?? group.querySelector('[data-configurable-option]:checked')?.value
                ?? '';
        };

        const optionControls = (group) => Array.from(
            group.querySelectorAll('[data-configurable-option]'),
        );

        const clearSelection = (group) => {
            const select = group.querySelector('[data-configurable-select]');

            if (select) {
                select.value = '';

                return;
            }

            group.querySelectorAll('[data-configurable-option]:checked').forEach((option) => {
                option.checked = false;
            });
        };

        const selections = (excludedAttribute = null, optionOverride = null) => {
            const selected = {};

            attributeGroups.forEach((group) => {
                const attributeId = group.dataset.configurableAttribute;
                const value = attributeId === excludedAttribute
                    ? optionOverride
                    : selectedValue(group);

                if (value) {
                    selected[attributeId] = Number(value);
                }
            });

            return selected;
        };

        const matches = (variant, selected) => Object.entries(selected)
            .every(([attributeId, optionId]) => variant.options[attributeId] === optionId);

        const setPurchasable = (enabled, maximum = 0) => {
            quantity.disabled = !enabled;
            quantityButtons.forEach((button) => {
                button.disabled = !enabled;
            });
            submit.disabled = !enabled;

            if (enabled) {
                quantity.min = '1';
                quantity.max = maximum;
                const current = Number(quantity.value);
                quantity.value = !Number.isInteger(current) || current < 1
                    ? 1
                    : Math.min(current, Number(maximum));
            } else {
                quantity.value = '0';
                quantity.max = '0';
            }
        };

        const renderPrice = (variant = null) => {
            priceContainer.replaceChildren();

            if (!variant) {
                priceContainer.append(...initialPriceNodes.map((node) => node.cloneNode(true)));

                return;
            }

            const current = document.createElement('span');
            current.className = 'h4 fw-bold text-primary mb-0';
            current.textContent = variant.price;
            priceContainer.append(current);

            if (variant.regular_price) {
                const regular = document.createElement('span');
                regular.className = 'text-muted text-decoration-line-through ms-2';
                regular.textContent = variant.regular_price;
                priceContainer.append(regular);
            }

            if (variant.tax_label) {
                const tax = document.createElement('small');
                tax.className = 'd-block text-muted mt-1';
                tax.textContent = variant.tax_label;
                priceContainer.append(tax);
            }
        };

        const renderState = () => {
            let clearedSelection;

            do {
                clearedSelection = false;
                attributeGroups.forEach((group) => {
                    const attributeId = group.dataset.configurableAttribute;

                    optionControls(group).forEach((option) => {
                        const candidateSelections = selections(attributeId, Number(option.value));
                        option.disabled = !variants.some((variant) => matches(variant, candidateSelections));
                    });

                    const selectedOption = optionControls(group).find((option) => (
                        option.value === selectedValue(group)
                    ));
                    if (selectedOption?.disabled) {
                        clearSelection(group);
                        clearedSelection = true;
                    }
                });
            } while (clearedSelection);

            const selected = selections();
            const complete = Object.keys(selected).length === attributeGroups.length;
            const variant = complete
                ? variants.find((candidate) => matches(candidate, selected))
                : null;

            renderPrice(variant);

            if (sku && skuValue) {
                sku.classList.toggle('d-none', !variant);
                skuValue.textContent = variant?.sku ?? '';
            }

            mainImages.forEach((image) => {
                const imageUrl = variant?.image_url || parentImage;

                if (imageUrl) {
                    image.setAttribute('src', imageUrl);
                } else {
                    image.removeAttribute('src');
                }

                image.classList.toggle('d-none', !imageUrl);
            });
            imagePlaceholders.forEach((placeholder) => {
                placeholder.classList.toggle('d-none', Boolean(variant?.image_url || parentImage));
            });

            availability?.classList.toggle('bg-success', Boolean(variant?.in_stock));
            availability?.classList.toggle('bg-danger', !variant?.in_stock);
            availabilityIcon?.classList.toggle('bi-check-lg', Boolean(variant?.in_stock));
            availabilityIcon?.classList.toggle('bi-x-lg', !variant?.in_stock);

            if (!complete) {
                availabilityLabel.textContent = form.dataset.selectLabel;
                setPurchasable(false);
            } else if (!variant) {
                availabilityLabel.textContent = form.dataset.unavailableLabel;
                setPurchasable(false);
            } else {
                availabilityLabel.textContent = variant.available_label;
                setPurchasable(variant.in_stock, variant.available_quantity);
            }
        };

        attributeGroups.forEach((group) => {
            group.addEventListener('change', renderState);
        });
        renderState();
    });
}
