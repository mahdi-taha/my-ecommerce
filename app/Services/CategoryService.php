<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CategoryService
{
    private const MAX_LEVEL = 2;

    private const MAX_LEVEL_MESSAGE = 'Categories can only have three hierarchy levels.';

    public function create(array $data): Category
    {
        $logo = $this->storeImage($data['logo'] ?? null, 'categories/logos');
        $banner = $this->storeImage($data['banner'] ?? null, 'categories/banners');

        try {
            return DB::transaction(function () use ($data, $logo, $banner) {
                $parent = ! empty($data['parent_id']) ? Category::whereKey($data['parent_id'])->lockForUpdate()->firstOrFail() : null;
                $this->ensureParentCanHaveChildren($parent);
                $category = Category::create([
                    'parent_id' => $parent?->id, 'position' => $data['position'],
                    'level' => $parent ? $parent->level + 1 : 0, 'logo_path' => $logo,
                    'banner_path' => $banner, 'status' => $data['status'],
                ]);
                $this->syncTranslations($category, $data);
                $category->filterableAttributes()->sync($data['filterable_attributes'] ?? []);

                return $category;
            });
        } catch (Throwable $exception) {
            $this->deleteFiles([$logo, $banner]);
            throw $exception;
        }
    }

    public function update(Category $category, array $data): Category
    {
        $newLogo = $this->storeImage($data['logo'] ?? null, 'categories/logos');
        $newBanner = $this->storeImage($data['banner'] ?? null, 'categories/banners');
        $oldLogo = $category->logo_path;
        $oldBanner = $category->banner_path;

        try {
            $result = DB::transaction(function () use ($category, $data, $newLogo, $newBanner) {
                $locked = Category::whereKey($category->id)->lockForUpdate()->firstOrFail();
                $descendantLevels = $this->descendantLevels($locked, true);
                $descendants = array_keys($descendantLevels);
                if (! empty($data['parent_id']) && (
                    (int) $data['parent_id'] === (int) $locked->id
                    || in_array((int) $data['parent_id'], $descendants, true)
                )) {
                    throw ValidationException::withMessages(['parent_id' => 'A descendant category cannot be selected as the parent.']);
                }
                $parent = ! empty($data['parent_id']) ? Category::whereKey($data['parent_id'])->lockForUpdate()->firstOrFail() : null;
                $newLevel = $parent ? $parent->level + 1 : 0;
                $deepestRelativeLevel = $descendantLevels === [] ? 0 : max($descendantLevels);
                if ($newLevel + $deepestRelativeLevel > self::MAX_LEVEL) {
                    $this->throwMaximumLevelValidation();
                }
                $locked->update([
                    'parent_id' => $parent?->id, 'position' => $data['position'], 'level' => $newLevel,
                    'logo_path' => $newLogo ?? $locked->logo_path, 'banner_path' => $newBanner ?? $locked->banner_path,
                    'status' => $data['status'],
                ]);
                $this->syncTranslations($locked, $data);
                $locked->filterableAttributes()->sync($data['filterable_attributes'] ?? []);
                foreach (collect($descendantLevels)->groupBy(
                    fn (int $relativeLevel): int => $relativeLevel,
                    preserveKeys: true
                ) as $relativeLevel => $ids) {
                    Category::query()->whereKey($ids->keys())->update([
                        'level' => $newLevel + (int) $relativeLevel,
                    ]);
                }

                return $locked->refresh();
            });
        } catch (Throwable $exception) {
            $this->deleteFiles([$newLogo, $newBanner]);
            throw $exception;
        }

        $this->deleteFiles([$newLogo ? $oldLogo : null, $newBanner ? $oldBanner : null]);

        return $result;
    }

    public function descendantIds(Category $category, bool $lock = false): array
    {
        return array_keys($this->descendantLevels($category, $lock));
    }

    /** @return array<int, int> category id => depth relative to the supplied category */
    private function descendantLevels(Category $category, bool $lock = false): array
    {
        $result = [];
        $parents = [$category->id];
        $visited = [$category->id];
        $relativeLevel = 1;
        while ($parents !== []) {
            $query = Category::whereIn('parent_id', $parents)->orderBy('id');
            if ($lock) {
                $query->lockForUpdate();
            }
            $children = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
            $children = array_values(array_diff($children, $visited));
            foreach ($children as $child) {
                $result[$child] = $relativeLevel;
            }
            $visited = array_merge($visited, $children);
            $parents = $children;
            $relativeLevel++;
        }

        return $result;
    }

    private function ensureParentCanHaveChildren(?Category $parent): void
    {
        if ($parent && $parent->level >= self::MAX_LEVEL) {
            $this->throwMaximumLevelValidation();
        }
    }

    private function throwMaximumLevelValidation(): never
    {
        throw ValidationException::withMessages([
            'parent_id' => self::MAX_LEVEL_MESSAGE,
        ]);
    }

    public function delete(Category $category): void
    {
        $imagePaths = DB::transaction(function () use ($category) {
            $category = Category::query()
                ->whereKey($category->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($category->children()->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'A category with child categories cannot be deleted.',
                ]);
            }

            if ($category->products()->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'A category containing products cannot be deleted.',
                ]);
            }

            $imagePaths = [$category->logo_path, $category->banner_path];
            $category->delete();

            return $imagePaths;
        });

        $this->deleteFiles($imagePaths);
    }

    private function syncTranslations(Category $category, array $data): void
    {
        foreach (['en', 'ar'] as $locale) {
            $category->translations()->updateOrCreate(['locale' => $locale], [
                'name' => $data['category_name_'.$locale], 'slug' => $data['category_slug_'.$locale],
                'meta_title' => $data['meta_title_'.$locale] ?? null,
                'meta_description' => $data['meta_description_'.$locale] ?? null,
                'meta_keywords' => $data['meta_keywords_'.$locale] ?? null,
            ]);
        }
    }

    private function storeImage(?UploadedFile $file, string $directory): ?string
    {
        if (! $file) {
            return null;
        }
        $path = $file->store($directory, 'public');
        if (! is_string($path) || trim($path) === '') {
            throw new RuntimeException('The category image could not be stored.');
        }

        return $path;
    }

    private function deleteFiles(array $paths): void
    {
        $paths = array_values(array_filter($paths));
        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }
}
