<?php

namespace App\Http\Controllers;

use App\Models\BlogFaqModel;
use App\Models\BlogModel;
use App\Models\BlogTagModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $limit = max(1, (int) $request->input('limit', 10));
        $offset = max(0, (int) $request->input('offset', 0));
        $tag = trim((string) $request->input('tag', ''));
        $search = trim((string) $request->input('search', ''));

        $q = BlogModel::query()
            ->with(['tags:id,name,slug'])
            ->where('is_published', true);

        if ($search !== '') {
            $q->where(function ($sq) use ($search) {
                $sq->where('title', 'like', '%' . $search . '%')
                    ->orWhere('sub_title', 'like', '%' . $search . '%');
            });
        }

        if ($tag !== '') {
            $q->whereHas('tags', function ($tq) use ($tag) {
                $tq->where('slug', $tag)->orWhere('name', 'like', '%' . $tag . '%');
            });
        }

        $total = (clone $q)->count();
        $blogs = $q->orderByDesc('published_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Blogs fetched successfully.',
            'data' => [
                'total' => $total,
                'count' => $blogs->count(),
                'limit' => $limit,
                'offset' => $offset,
                'blogs' => $blogs,
            ],
        ], 200);
    }

    public function showBySlug(string $slug)
    {
        $blog = BlogModel::with(['tags:id,name,slug', 'faqs:id,blog_id,question,answer,sort_order'])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if (! $blog) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Blog not found.',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Blog fetched successfully.',
            'data' => $blog,
        ], 200);
    }

    public function adminIndex(Request $request, $id = null)
    {
        if ($id !== null && $id !== '') {
            $blog = BlogModel::with([
                'tags:id,name,slug',
                'faqs:id,blog_id,question,answer,sort_order',
            ])->find($id);

            if (! $blog) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'Blog not found.',
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Blog fetched successfully.',
                'data' => $blog,
            ], 200);
        }

        $limit = max(1, (int) $request->input('limit', 10));
        $offset = max(0, (int) $request->input('offset', 0));
        $status = $request->input('is_published', null);
        $search = trim((string) $request->input('search', ''));

        $q = BlogModel::query()->with(['tags:id,name,slug']);

        if ($search !== '') {
            $q->where(function ($sq) use ($search) {
                $sq->where('title', 'like', '%' . $search . '%')
                    ->orWhere('sub_title', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%');
            });
        }

        if ($status !== null && $status !== '') {
            $q->where('is_published', filter_var($status, FILTER_VALIDATE_BOOLEAN));
        }

        $total = (clone $q)->count();
        $blogs = $q->orderByDesc('id')->offset($offset)->limit($limit)->get();

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Blogs fetched successfully.',
            'data' => [
                'total' => $total,
                'count' => $blogs->count(),
                'limit' => $limit,
                'offset' => $offset,
                'blogs' => $blogs,
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $slug = $this->resolveSlug($validated['slug'] ?? null, $validated['title']);
        $validated['slug'] = $this->uniqueSlug($slug);
        $coverImagePath = $this->storeCoverImage($request);

        $blog = DB::transaction(function () use ($validated, $coverImagePath) {
            $blogData = $this->extractBlogData($validated);
            if ($coverImagePath !== null) {
                $blogData['cover_image'] = $coverImagePath;
            }
            if (array_key_exists('is_published', $validated)) {
                $blogData['is_published'] = filter_var($validated['is_published'], FILTER_VALIDATE_BOOLEAN);
            }
            $blog = BlogModel::create($blogData);
            $this->syncTags($blog, $validated['tags'] ?? []);
            $this->replaceFaqs($blog, $validated['faqs'] ?? []);
            return $blog->load(['tags:id,name,slug', 'faqs:id,blog_id,question,answer,sort_order']);
        });

        return response()->json([
            'code' => 201,
            'success' => true,
            'message' => 'Blog created successfully.',
            'data' => $blog,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $blog = BlogModel::find($id);
        if (! $blog) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Blog not found.',
                'data' => [],
            ], 404);
        }

        $validated = $this->validatePayload($request, true);
        $slugBase = $validated['slug'] ?? $blog->slug;
        if (! empty($validated['title']) && empty($validated['slug'])) {
            $slugBase = $this->resolveSlug(null, $validated['title']);
        }
        $validated['slug'] = $this->uniqueSlug($slugBase, $blog->id);
        $coverImagePath = $this->storeCoverImage($request);
        $removeCoverImage = $request->boolean('remove_cover_image');

        $updated = DB::transaction(function () use ($blog, $validated, $coverImagePath, $removeCoverImage) {
            $blogData = $this->extractBlogData($validated);
            if ($coverImagePath !== null) {
                // Remove previous file to avoid orphaned uploads.
                if (! empty($blog->cover_image)) {
                    Storage::disk('public')->delete($blog->cover_image);
                }
                $blogData['cover_image'] = $coverImagePath;
            } elseif ($removeCoverImage) {
                if (! empty($blog->cover_image)) {
                    Storage::disk('public')->delete($blog->cover_image);
                }
                $blogData['cover_image'] = null;
            }
            if (array_key_exists('is_published', $validated)) {
                $blogData['is_published'] = filter_var($validated['is_published'], FILTER_VALIDATE_BOOLEAN);
            }
            $blog->update($blogData);

            if (array_key_exists('tags', $validated)) {
                $this->syncTags($blog, $validated['tags'] ?? []);
            }
            if (array_key_exists('faqs', $validated)) {
                $this->replaceFaqs($blog, $validated['faqs'] ?? []);
            }

            return $blog->load(['tags:id,name,slug', 'faqs:id,blog_id,question,answer,sort_order']);
        });

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Blog updated successfully.',
            'data' => $updated,
        ], 200);
    }

    public function destroy(int $id)
    {
        $blog = BlogModel::find($id);
        if (! $blog) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Blog not found.',
                'data' => [],
            ], 404);
        }

        $blog->delete();

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Blog deleted successfully.',
            'data' => [],
        ], 200);
    }

    private function validatePayload(Request $request, bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        $validated = $request->validate([
            'title' => $required . '|string|max:255',
            'sub_title' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255',
            'content' => $required . '|string',
            'cover_image' => 'nullable|file|image|max:5120',
            'remove_cover_image' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'canonical_url' => 'nullable|string|max:1000',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string',
            'og_image' => 'nullable|string|max:1000',
            'is_published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
            'faqs' => 'nullable|array',
            'faqs.*.question' => 'required_with:faqs|string|max:500',
            'faqs.*.answer' => 'required_with:faqs|string',
            'faqs.*.sort_order' => 'nullable|integer|min:0',
        ]);

        if (! empty($validated['canonical_url']) && ! filter_var($validated['canonical_url'], FILTER_VALIDATE_URL)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'canonical_url' => ['The canonical URL must be a valid URL.'],
            ]);
        }

        return $validated;
    }

    private function resolveSlug(?string $incoming, string $title): string
    {
        $base = trim((string) $incoming);
        if ($base === '') {
            $base = $title;
        }
        $slug = Str::slug($base);
        return $slug !== '' ? $slug : 'blog';
    }

    private function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = $slug;
        $i = 1;
        while (true) {
            $q = BlogModel::where('slug', $slug);
            if ($ignoreId) {
                $q->where('id', '!=', $ignoreId);
            }
            if (! $q->exists()) {
                return $slug;
            }
            $slug = $base . '-' . $i;
            $i++;
        }
    }

    private function extractBlogData(array $validated): array
    {
        $keys = [
            'title', 'sub_title', 'slug', 'content',
            'meta_title', 'meta_description', 'meta_keywords', 'canonical_url',
            'og_title', 'og_description', 'og_image', 'is_published', 'published_at',
        ];

        $payload = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $validated)) {
                $payload[$key] = $validated[$key];
            }
        }

        return $payload;
    }

    private function storeCoverImage(Request $request): ?string
    {
        if (! $request->hasFile('cover_image')) {
            return null;
        }

        $file = $request->file('cover_image');
        if (! $file || ! $file->isValid()) {
            return null;
        }

        return $file->store('blogs/covers', 'public');
    }

    private function syncTags(BlogModel $blog, array $tags): void
    {
        $tagIds = collect($tags)
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v) => trim($v))
            ->unique()
            ->map(function ($name) {
                $slug = Str::slug($name);
                if ($slug === '') {
                    $slug = 'tag';
                }

                $base = $slug;
                $i = 1;
                while (true) {
                    $tag = BlogTagModel::where('slug', $slug)->first();
                    if ($tag) {
                        return $tag->id;
                    }

                    if (! BlogTagModel::where('name', $name)->exists()) {
                        return BlogTagModel::create([
                            'name' => $name,
                            'slug' => $slug,
                        ])->id;
                    }

                    $slug = $base . '-' . $i;
                    $i++;
                }
            })
            ->values()
            ->all();

        $blog->tags()->sync($tagIds);
    }

    private function replaceFaqs(BlogModel $blog, array $faqs): void
    {
        BlogFaqModel::where('blog_id', $blog->id)->delete();

        foreach ($faqs as $idx => $faq) {
            if (empty($faq['question']) || empty($faq['answer'])) {
                continue;
            }
            BlogFaqModel::create([
                'blog_id' => $blog->id,
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'sort_order' => $faq['sort_order'] ?? $idx,
            ]);
        }
    }
}
