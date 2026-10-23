<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnboardingSlide;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;


class OnboardingSlideController extends Controller
{
    /**
     * Validation rules shared by create/update.
     */
    private function rules(bool $creating = true, ?int $ignoreId = null): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'key' => [
                $required,
                'string',
                'max:50',
                $ignoreId
                    ? Rule::unique('onboarding_slides', 'key')->ignore($ignoreId)
                    : Rule::unique('onboarding_slides', 'key'),
            ],
            // Cloudinary URL of the slide illustration.
            'image_url' => [$required, 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:200'],
            'title_km' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'description_km' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    
    public function index()
    {
        $slides = OnboardingSlide::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'message' => 'success',
            'onboarding_slides' => $slides,
        ]);
    }

    /**
     * GET /api/onboarding-slides/{id}
     */
    public function show($id)
    {
        $slide = OnboardingSlide::find($id);

        if (! $slide) {
            return response()->json([
                'message' => 'Onboarding slide not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'success',
            'onboarding_slide' => $slide,
        ]);
    }

    /**
     * POST /api/onboarding-slides
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $slide = OnboardingSlide::create($data);

        return response()->json([
            'message' => 'Onboarding slide created successfully.',
            'onboarding_slide' => $slide,
        ], Response::HTTP_CREATED);
    }

   
    public function update(Request $request, $id)
    {
        $slide = OnboardingSlide::find($id);

        if (! $slide) {
            return response()->json([
                'message' => 'Onboarding slide not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = collect($request->validate(
            $this->rules(creating: false, ignoreId: $slide->id)
        ))->filter(fn ($value) => $value !== null)->all();

        $slide->update($data);

        return response()->json([
            'message' => 'Onboarding slide updated successfully.',
            'onboarding_slide' => $slide->fresh(),
        ]);
    }

    /**
     * DELETE /api/onboarding-slides/{id}
     */
    public function destroy($id)
    {
        $slide = OnboardingSlide::find($id);

        if (! $slide) {
            return response()->json([
                'message' => 'Onboarding slide not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $slide->delete();

        return response()->json([
            'message' => 'Onboarding slide deleted successfully.',
        ]);
    }

    /**
     * POST /api/onboarding-slides/{id}/image
     *
     * Uploads the slide illustration to Cloudinary and stores the resulting
     * URL on the onboarding slide.
     */
    public function uploadImage(Request $request, $id, CloudinaryService $cloudinary)
    {
        $slide = OnboardingSlide::find($id);

        if (! $slide) {
            return response()->json([
                'message' => 'Onboarding slide not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $request->validate([
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:4096'],
        ]);

        $uploadedFile = $request->file('image');

        // Upload to Cloudinary and store the secure URL on the record.
        // Pass the real temp file path (string) — the raw cloudinary_php SDK
        // does not accept a Laravel UploadedFile object directly.
        $imageUrl = $cloudinary->uploadAndGetUrl(
            $uploadedFile->getRealPath(),
            [],
            'tinh_shop/onboarding'
        );

        $slide->update([
            'image_url' => $imageUrl,
        ]);

        return response()->json([
            'message' => 'Image uploaded successfully.',
            'onboarding_slide' => $slide->fresh(),
        ]);
    }
}
