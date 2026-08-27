<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Files people upload, written straight into public/.
 *
 * NOT the `public` filesystem disk. That one points at storage/app/public and
 * only becomes reachable over HTTP once `php artisan storage:link` has been
 * run — a step that is easy to miss, produces no error when missed, and shows
 * up as every uploaded photograph 404ing on a machine where the deploy script
 * forgot it. Writing under public/ directly means an uploaded file is served
 * the moment it lands, on any machine, with no extra step.
 *
 * The path handed back is RELATIVE — `img/properties/xyz.jpg` — because that
 * is what goes in the database. PropertyPhotos::resolve() turns it into a URL
 * at render time, which is also what lets the same column hold a full URL to
 * somebody else's CDN without either case needing to know about the other.
 */
final class ImageStore
{
    /** What a browser will actually render. SVG is excluded on purpose. */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** Everything a realtor might be handed to send on. */
    public const DOCUMENT_EXTENSIONS = ['pdf', 'mp4', 'webm'];

    /**
     * Move an upload into public/{$folder} and return its relative path.
     *
     * The stored name is generated rather than taken from the upload. A file
     * called "3 bed, Lekki (FINAL).jpg" produces a URL nobody can paste, and a
     * file called "../../.env" produces something worse — Str::random plus a
     * whitelisted extension makes both impossible rather than merely unlikely.
     */
    public static function put(UploadedFile $file, string $folder): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');

        if (! in_array($extension, [...self::IMAGE_EXTENSIONS, ...self::DOCUMENT_EXTENSIONS], true)) {
            throw new \InvalidArgumentException("Cannot store a .{$extension} file.");
        }

        $folder = trim($folder, '/');
        $directory = public_path($folder);

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, recursive: true);
        }

        // Prefixed with the date so the folder stays browsable by eye when
        // somebody eventually has to go and find one on the server.
        $name = now()->format('Ymd').'-'.Str::random(12).'.'.$extension;
        $target = $directory.DIRECTORY_SEPARATOR.$name;

        /**
         * Copied through, not moved.
         *
         * UploadedFile::move() ultimately calls move_uploaded_file(), which by
         * design only accepts a file PHP itself received over HTTP in this
         * request. A Livewire upload is not that — it was received on an
         * earlier request and parked on a disk — so move() fails with a bare
         * "could not move the file" and the listing saves with no photograph.
         *
         * get() is defined for both a real upload and Livewire's temporary
         * file, and works whichever disk the temporary one is parked on.
         * Livewire sweeps its own temp directory, so nothing is left behind.
         */
        if (file_put_contents($target, $file->get()) === false) {
            throw new \RuntimeException("Could not write the upload to {$folder}.");
        }

        return $folder.'/'.$name;
    }

    /**
     * Delete a file this class wrote.
     *
     * Refuses anything that is not a relative path inside the folder it was
     * given, which is what stops a tampered form value from unlinking
     * something else on the box. A remote URL is silently ignored rather than
     * treated as an error: removing an image that lives on somebody else's
     * server is a database edit, and that has already happened by the time
     * this is called.
     */
    public static function forget(?string $path, string $folder): void
    {
        $path = trim((string) $path);
        $folder = trim($folder, '/');

        if ($path === '' || ! str_starts_with($path, $folder.'/')) {
            return;
        }

        // Belt and braces: a path that traverses out of public/ never reaches
        // unlink even if it somehow satisfied the prefix check above.
        $full = realpath(public_path($path));
        $root = realpath(public_path($folder));

        if ($full && $root && str_starts_with($full, $root) && is_file($full)) {
            @unlink($full);
        }
    }

    /** Validation rule for an image upload, shared by every form that takes one. */
    public static function imageRule(int $maxKb = 6144): array
    {
        return ['image', 'mimes:'.implode(',', self::IMAGE_EXTENSIONS), 'max:'.$maxKb];
    }

    /** Validation rule for a flyer, brochure or clip. */
    public static function assetRule(int $maxKb = 20480): array
    {
        return [
            'file',
            'mimes:'.implode(',', [...self::IMAGE_EXTENSIONS, ...self::DOCUMENT_EXTENSIONS]),
            'max:'.$maxKb,
        ];
    }
}
