<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAttributeOptionsRequest;
use App\Models\Attribute;
use App\Services\AttributeService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class AttributeOptionController extends Controller
{
    public function __construct(private AttributeService $attributeService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Attribute $attribute): View
    {
        abort_unless(
            in_array($attribute->type, ['select', 'multiselect']),
            404
        );

        $attribute->load([
            'translations',
            'options' => fn ($query) => $query
                ->with('translations')
                ->withExists(['productValues', 'productSuperAttributes']),
        ]);

        return view(
            'admin.attributes.options',
            compact('attribute')
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function save(SaveAttributeOptionsRequest $request, Attribute $attribute): JsonResponse
    {
        abort_unless(
            in_array($attribute->type, ['select', 'multiselect']),
            404
        );

        $this->attributeService->saveOptions($attribute, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Options saved successfully.',
        ]);
    }
}
