<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaveRequest;
use App\Http\Requests\UpdateCaveRequest;
use App\Http\Resources\CaveResource;
use App\Models\Cave;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class CaveController extends Controller
{
    public function index()
    {
        return CaveResource::collection(Cave::orderBy('name')->get());
    }

    public function store(StoreCaveRequest $request)
    {
        Cave::create($request->all())->save();
    }

    public function show(Cave $cave)
    {
        return new CaveResource($cave);
    }

    public function update(UpdateCaveRequest $request, Cave $cave)
    {
        $cave->update($request->all());
        // Update tags
        $tags = collect($request->all()['tags'])->map(function ($tag) {
            return \App\Models\Tag::where([
                'category' => $tag['category'],
                'tag' => $tag['tag'],
                'assignable' => true
            ])->first()?->id;
        })->filter();
        $cave->tags()->sync($tags);

        // Save the hero image
        if ($request->has('hero_image') && $request->get('hero_image') !== null) {
            // check if hero image is url
            if (is_array($request->get('hero_image'))) {
                $fileData = explode(',', $request->get('hero_image')['data']);
                $image = Image::read($fileData[1], [
                    \Intervention\Image\Decoders\DataUriImageDecoder::class,
                    \Intervention\Image\Decoders\Base64ImageDecoder::class,
                ])->scaleDown(2048, 2048)->encode(new \Intervention\Image\Encoders\WebpEncoder(quality: 65));
                $filePath = 'caves/' . \Illuminate\Support\Str::uuid() . '_hero.webp';
                Storage::disk('media')->put($filePath, (string) $image);
                $cave->hero_image = $filePath;
                $cave->save();
            }
        } else {
            $cave->hero_image = null;// Ensure any image which was on cave has been removed
            $cave->save();
        }
    }

    // public function destroy(Cave $cave)
    // {
    //     Cave::destroy($cave);
    // }
}
