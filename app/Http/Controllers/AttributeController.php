<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttributeRequest;
use App\Http\Requests\UpdateAttributeRequest;
use App\Models\Attribute;
use App\Services\AttributeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class AttributeController extends Controller
{
    public function __construct(private AttributeService $attributeService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {

            $data = Attribute::query()
                ->with([
                    'translations' => function ($query) {
                        $query->where('locale', 'en');
                    },
                ]);

            return DataTables::eloquent($data)
                ->addColumn('admin_name', function (Attribute $attribute) {
                    return $attribute->translations->first()?->admin_name ?? '-';
                })
                ->editColumn('type', function (Attribute $attribute) {
                    return ucfirst($attribute->type);
                })
                ->editColumn('is_required', function (Attribute $attribute) {
                    return $attribute->is_required ? 'Yes' : 'No';
                })
                ->editColumn('is_configurable', function (Attribute $attribute) {
                    return $attribute->is_configurable ? 'Yes' : 'No';
                })
                ->editColumn('is_filterable', function (Attribute $attribute) {
                    return $attribute->is_filterable ? 'Yes' : 'No';
                })
                ->editColumn('is_active', function (Attribute $attribute) {
                    return $attribute->is_active
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>';
                })
                ->addColumn('action', function (Attribute $attribute) {

                    $editUrl = route('admin.attributes.edit', $attribute->id);
                    $deleteUrl = route('admin.attributes.destroy', $attribute->id);
                    $optionsUrl = route(
                        'admin.attribute-options.index',
                        $attribute->id
                    );

                    $buttons = '<span class="d-flex gap-2">';

                    if (in_array($attribute->type, ['select', 'multiselect'])) {
                        $buttons .= '
                        <a href="'.$optionsUrl.'" class="btn text-dark p-0"
                           title="Options">
                            <i class="ti ti-circle-plus fs-6"></i>
                        </a>
                    ';
                    }

                    $buttons .= '
                    <a href="'.$editUrl.'" class="btn text-primary p-0"
                       title="Edit">
                        <i class="ti ti-edit fs-6"></i>
                    </a>
                ';

                    $buttons .= '
                    <button type="button" class="btn text-danger p-0 attribute-delete"
                        data-url="'.$deleteUrl.'" data-code="'.e($attribute->code).'" title="Delete">
                        <i class="ti ti-trash fs-6"></i>
                    </button>
                ';

                    $buttons .= '</span>';

                    return $buttons;
                })
                ->addIndexColumn()
                ->rawColumns(['is_active', 'action'])
                ->toJson();
        }

        return view('admin.attributes.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.attributes.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAttributeRequest $request)
    {
        $validated = $request->validated();
        $attribute = $this->attributeService->create($validated);

        if (in_array($validated['attribute_type'], ['select', 'multiselect'])) {
            return redirect()
                ->route('admin.attribute-options.index', $attribute)
                ->with('success', 'Attribute updated successfully.');
        }

        return redirect()
            ->route('admin.attributes.index')
            ->with('success', 'Attribute created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Attribute $attribute)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Attribute $attribute)
    {
        $attribute->load('translations');
        $en_name = $attribute->translations->firstWhere('locale', 'en');
        $ar_name = $attribute->translations->firstWhere('locale', 'ar');

        return view('admin.attributes.edit', compact('attribute', 'en_name', 'ar_name'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAttributeRequest $request, Attribute $attribute)
    {
        $this->attributeService->update($attribute, $request->validated());

        return redirect()
            ->route('admin.attributes.index')
            ->with('success', 'Attribute updated successfully.');

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attribute $attribute): JsonResponse
    {
        $this->attributeService->delete($attribute);

        return response()->json([
            'message' => 'Attribute deleted successfully.',
        ]);
    }
}
