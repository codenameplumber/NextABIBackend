<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a realtor can send a prospect.
 *
 * Stored once, personalised on the way out. Admin uploads one flyer; every
 * realtor downloads it with their own code and link stamped in. The alternative
 * — a copy per realtor — means a corrected price has to be re-cut a hundred
 * times, and it never is.
 */
class PromotionalMaterial extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['active' => 'bool', 'downloads' => 'int'];

    public const KINDS = [
        'flyer' => 'Flyer',
        'image' => 'Social image',
        'brochure' => 'Brochure',
        'video' => 'Video',
        'copy' => 'Message template',
    ];

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    /**
     * Kinds that ARE a file rather than kinds that merely have one.
     *
     * A flyer with no artwork is not a flyer, it is a title — so these require
     * an asset, and `copy` requires body text instead. Splitting on this is
     * what lets one form serve both without asking for everything every time.
     */
    public const VISUAL_KINDS = ['flyer', 'image', 'brochure', 'video'];

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? 'Material';
    }

    public function isVisual(): bool
    {
        return in_array($this->kind, self::VISUAL_KINDS, true);
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }

    /** Something a browser can render inline, as opposed to a PDF or a clip. */
    public function isPreviewable(): bool
    {
        return $this->hasFile() && in_array(
            strtolower(pathinfo((string) $this->file_path, PATHINFO_EXTENSION)),
            \App\Support\ImageStore::IMAGE_EXTENSIONS,
            true,
        );
    }

    /** Resolved the same way a property photograph is — path or full URL. */
    public function assetUrl(): ?string
    {
        return \App\Support\PropertyPhotos::resolve($this->file_path);
    }

    /**
     * A message template with the realtor's own details filled in.
     *
     * Placeholders rather than concatenation so the wording stays editable by
     * whoever writes the copy, not by whoever maintains this class.
     */
    public function personalise(Agent $realtor): string
    {
        return strtr((string) $this->body, [
            '{realtor}' => $realtor->name,
            '{first_name}' => $realtor->firstName(),
            '{phone}' => (string) $realtor->phone,
            '{code}' => (string) $realtor->code,
            '{link}' => $realtor->registrationLink(),
        ]);
    }
}
