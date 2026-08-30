<?php

declare(strict_types = 1);

namespace Modufolio\Media\Service;

use Modufolio\Media\Contract\UploadedImageHookInterface;
use Modufolio\Media\Entity\Media;
use Modufolio\Media\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use claviska\SimpleImage;
use kornrunner\Blurhash\Blurhash;
use Modufolio\Appkit\Image\Darkroom\GdLib;
use Modufolio\Appkit\Image\DarkroomInterface;
use Modufolio\Appkit\Image\DiskManager;
use Modufolio\Appkit\Image\Image;
use Modufolio\Appkit\Image\StorageInterface;
use Modufolio\Appkit\Security\Token\TokenStorageInterface;
use Modufolio\Appkit\Toolkit\Str;

class MediaUploadProcessingService
{
    private const BLOCKED_MIME_TYPES = [
        'text/html',
        'text/javascript',
        'application/javascript',
        'application/x-php',
        'text/x-php',
        'application/x-httpd-php',
        'image/svg+xml',
    ];

    /**
     * Executable / script extensions that must never be stored, regardless of
     * the sniffed MIME type. Defence in depth for non-image uploads so a file
     * cannot land in a web-served directory with an interpretable extension.
     */
    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'pht', 'phps',
        'html', 'htm', 'shtml', 'xhtml', 'svg', 'svgz', 'xml',
        'js', 'mjs', 'cjs', 'htaccess', 'pl', 'py', 'cgi', 'asp', 'aspx', 'jsp', 'sh',
    ];

    /**
     * The only raster image types we accept. The map also yields the canonical,
     * safe extension we force onto the stored master so neither it nor any
     * derived rendition can carry an attacker-chosen extension.
     */
    private const IMAGE_TYPE_EXTENSIONS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];

    private const MAX_DIMENSION = 2500;

    public function __construct(
        private readonly StorageInterface $storage,
        private readonly DiskManager $diskManager,
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ?UploadedImageHookInterface $imageHook = null,
        private readonly DarkroomInterface $darkroom = new GdLib(),
    ) {
    }

    public function saveUploadedFileToDatabase(string $filename): void
    {
        $uploadDir = rtrim($this->storage->uploadsDir(), '/') . '/tus';

        $filePath = $uploadDir . '/' . $filename;

        if (!file_exists($filePath)) {
            return;
        }

        // Content-hash dedup: reject byte-identical re-uploads before doing any
        // image work. Matched against both the raw-upload and stored-master
        // checksums of existing media (see findDuplicateByChecksum).
        $rawChecksum = sha1_file($filePath) ?: null;
        if ($rawChecksum !== null && $this->mediaRepository->findDuplicateByChecksum($rawChecksum) !== null) {
            unlink($filePath);
            return;
        }

        $clientExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Server-side content detection — never trust the client-supplied
        // extension or MIME header. getimagesize() confirms the bytes actually
        // decode as a known raster image and mime_content_type() sniffs via
        // libmagic. $filePath still points at the raw upload in the tus dir.
        $imageInfo = @getimagesize($filePath);
        $detectedImageExt = $imageInfo !== false
            ? (self::IMAGE_TYPE_EXTENSIONS[$imageInfo[2]] ?? null)
            : null;
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $looksLikeImage = $imageInfo !== false || str_starts_with($mimeType, 'image/');

        if ($looksLikeImage) {
            // Anything that presents as an image but does not decode as an
            // allow-listed raster type (corrupt file, SVG, polyglot, spoofed
            // MIME) is rejected outright rather than stored.
            if ($detectedImageExt === null) {
                unlink($filePath);
                return;
            }

            // Force the canonical extension from the detected type. This is the
            // key control: the stored master and every rendition derived from it
            // (written into the public/media docroot) can only ever carry a safe
            // image extension, never .php/.html/etc.
            $extension = $detectedImageExt;
        } else {
            // Non-image upload (document, video, …). Keep the server-detected
            // MIME blocklist and additionally refuse executable extensions.
            if (
                in_array($mimeType, self::BLOCKED_MIME_TYPES, true)
                || in_array($clientExtension, self::BLOCKED_EXTENSIONS, true)
            ) {
                unlink($filePath);
                return;
            }

            $extension = $clientExtension;
        }

        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $sluggedBase = Str::slug($basename) ?: 'file';
        $sluggedFilename = $extension !== '' ? $sluggedBase . '.' . $extension : $sluggedBase;

        $subfolder = substr(bin2hex($sluggedBase), 0, 5);
        $subDir = $uploadDir . '/' . $subfolder;

        $finalFilename = $sluggedFilename;
        $counter = 1;
        while (
            $this->mediaRepository->findByFilename($finalFilename) !== null
            || file_exists($subDir . '/' . $finalFilename)
        ) {
            $finalFilename = $sluggedBase . '-' . $counter . ($extension !== '' ? '.' . $extension : '');
            $counter++;
        }

        if (!is_dir($subDir)) {
            mkdir($subDir, 0755, true);
        }

        $finalFilePath = $subDir . '/' . $finalFilename;
        rename($filePath, $finalFilePath);

        $fileSize = filesize($finalFilePath) ?: 0;

        $media = new Media();
        $media->setFilename($finalFilename);
        $media->setOriginalFilename($filename);
        $media->setFilePath('/uploads/tus/' . $subfolder . '/' . $finalFilename);
        $media->setMimeType($mimeType);
        $media->setFileSize($fileSize);

        $currentUser = $this->tokenStorage->getToken()?->getUser();
        if ($currentUser instanceof \Modufolio\Media\Contract\UploaderInterface) {
            $media->setUploadedBy($currentUser);
        }

        if (str_starts_with($mimeType, 'image/')) {
            // Read EXIF/IPTC from the *original* bytes. Both downscaleInPlace()
            // and orientInPlace() re-encode through GD, which drops the EXIF and
            // APP13 blocks entirely — reading afterwards would yield nothing for
            // every image large enough to be downscaled.
            $sourceMetadata = $this->readSourceMetadata($finalFilePath);

            // Both rewrites below re-encode the pixels and drop EXIF, so the
            // bytes the photographer produced are gone once either runs. Copy
            // first and decide afterwards: whether a rewrite happens depends on
            // dimensions and an orientation flag, and duplicating that decision
            // here is how the two would drift apart.
            $preserved = $this->preserveOriginal($finalFilePath, $subDir, $finalFilename);

            // downscaleInPlace() already auto-orients as a side effect; only run a
            // dedicated orientation pass when downscaling was skipped (image already
            // within the size cap), so dimensions/thumbhash/crops all agree on "up".
            $rewritten = $this->downscaleInPlace($finalFilePath);
            if (!$rewritten) {
                $rewritten = $this->orientInPlace($finalFilePath, $mimeType);
            }
            if ($rewritten) {
                $media->setFileSize(filesize($finalFilePath) ?: $fileSize);
            }

            if ($preserved !== null && $rewritten) {
                $media->setOriginalPath('/uploads/originals/' . $subfolder . '/' . $finalFilename);
            } elseif ($preserved !== null) {
                // Nothing was rewritten, so the master *is* the original and a
                // second copy would cost storage while proving nothing.
                @unlink($preserved);
            }

            try {
                $image = new Image($finalFilePath, 'tus', $this->storage, $this->diskManager);
                $dimensions = $image->dimensions();
                $media->setWidth($dimensions->width());
                $media->setHeight($dimensions->height());

                $this->imageHook?->onImageStored($finalFilePath);
            } catch (\Exception) {
            }

            $media->setMetadata($sourceMetadata ?: null);
            $this->applyIptc($media, $sourceMetadata['iptc'] ?? []);

            try {
                $gdImage = match ($mimeType) {
                    'image/jpeg', 'image/jpg' => imagecreatefromjpeg($finalFilePath),
                    'image/png' => imagecreatefrompng($finalFilePath),
                    'image/gif' => imagecreatefromgif($finalFilePath),
                    'image/webp' => imagecreatefromwebp($finalFilePath),
                    default => null,
                };

                if ($gdImage !== null && $gdImage !== false) {
                    $thumbW = 64;
                    $thumbH = 64;
                    $thumb = imagecreatetruecolor($thumbW, $thumbH);
                    imagecopyresampled($thumb, $gdImage, 0, 0, 0, 0, $thumbW, $thumbH, imagesx($gdImage), imagesy($gdImage));
                    imagedestroy($gdImage);

                    $pixels = [];
                    for ($y = 0; $y < $thumbH; ++$y) {
                        $row = [];
                        for ($x = 0; $x < $thumbW; ++$x) {
                            $index = imagecolorat($thumb, $x, $y);
                            if ($index === false) {
                                continue;
                            }
                            $colors = imagecolorsforindex($thumb, $index);
                            $row[] = [$colors['red'], $colors['green'], $colors['blue']];
                        }
                        $pixels[] = $row;
                    }
                    imagedestroy($thumb);

                    $media->setThumbhash(Blurhash::encode($pixels, 4, 3));
                }
            } catch (\Exception) {
            }
        }

        $media->setOriginalChecksum($rawChecksum);
        // The master may have been rewritten in place (downscale/orient), so the
        // stored checksum is computed from the final bytes on disk.
        $media->setChecksum(sha1_file($finalFilePath) ?: $rawChecksum);

        $this->entityManager->persist($media);
        $this->entityManager->flush();
    }

    /**
     * Read EXIF and IPTC from an untouched upload.
     *
     * Must run before any in-place re-encode: GD does not carry the EXIF or
     * APP13 segments over, so camera data and the photographer's captions are
     * gone the moment the master is downscaled or auto-oriented.
     *
     * The flat EXIF keys are kept under their raw names because that is what
     * the panel's camera panel reads (MediaInspector.vue reads Model, FNumber,
     * ExposureTime, ISOSpeedRatings, FocalLength).
     *
     * @return array<string, mixed>
     */
    private function readSourceMetadata(string $path): array
    {
        $metadata = [];

        $exif = function_exists('exif_read_data') ? @exif_read_data($path) : false;
        if (is_array($exif)) {
            foreach (['Make', 'Model', 'Orientation', 'ExposureTime', 'ISOSpeedRatings', 'FocalLength', 'DateTimeOriginal'] as $key) {
                if (!isset($exif[$key]) || !is_scalar($exif[$key])) {
                    continue;
                }

                // Camera makers pad these fields to a fixed width
                // ("OLYMPUS IMAGING CORP.  "), which shows up verbatim in the UI.
                $value = is_string($exif[$key]) ? trim($exif[$key]) : $exif[$key];
                if ($value !== '') {
                    $metadata[$key] = $value;
                }
            }

            // The f-number lives in COMPUTED for most cameras; fall back to the
            // rational in the IFD0 block.
            $fNumber = $exif['COMPUTED']['ApertureFNumber'] ?? $exif['FNumber'] ?? null;
            if (is_scalar($fNumber)) {
                $metadata['FNumber'] = ltrim((string)$fNumber, 'f/');
            }

            $dateTaken = $this->parseDateTaken($exif);
            if ($dateTaken !== null) {
                $metadata['dateTaken'] = $dateTaken;
            }
        }

        $iptc = $this->readIptc($path);
        if ($iptc !== []) {
            $metadata['iptc'] = $iptc;
        }

        return $metadata;
    }

    /**
     * Resolve the capture date from EXIF.
     *
     * DateTimeOriginal is the only field that really means "when the shutter
     * fired"; DateTime is often the last edit. Both are tried through strtotime
     * because cameras emit `2013:01:01 00:00:00` as well as `2013/01/01 …`.
     * The file's own mtime is deliberately *not* a fallback — the master gets
     * rewritten on upload, so that would only ever record the upload time.
     *
     * @param array<string, mixed> $exif
     */
    private function parseDateTaken(array $exif): ?string
    {
        foreach (['DateTimeOriginal', 'DateTimeDigitized', 'DateTime'] as $key) {
            $raw = $exif[$key] ?? null;
            if (!is_string($raw) || $raw === '') {
                continue;
            }

            $timestamp = strtotime(str_replace(':', '-', substr($raw, 0, 10)) . substr($raw, 10));
            if ($timestamp !== false && $timestamp > 0) {
                return date(DATE_ATOM, $timestamp);
            }
        }

        return null;
    }

    /**
     * Read the IPTC/IIM block (JPEG APP13) an editor such as Lightroom writes.
     *
     * @return array{title?: string, description?: string, keywords?: list<string>}
     */
    private function readIptc(string $path): array
    {
        $info = [];
        if (@getimagesize($path, $info) === false || !isset($info['APP13'])) {
            return [];
        }

        $iptc = @iptcparse($info['APP13']);
        if (!is_array($iptc)) {
            return [];
        }

        // 1#090 carries the coded character set; `\x1b%G` means the records are
        // UTF-8. Without it the spec says ISO-8859-1, and that is also the safe
        // reading for the mislabelled files that show up in practice.
        $isUtf8 = ($iptc['1#090'][0] ?? '') === "\x1b%G";
        $decode = static function (string $value) use ($isUtf8): string {
            $value = trim($value);

            return ($isUtf8 || mb_check_encoding($value, 'UTF-8'))
                ? $value
                : mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        };

        $result = [];

        // Object Name (2#005) is the title; Adobe writes it there while others
        // use Headline (2#105), so prefer the former and fall back.
        $title = $iptc['2#005'][0] ?? $iptc['2#105'][0] ?? null;
        if ($title !== null && trim($title) !== '') {
            $result['title'] = $decode($title);
        }

        $description = $iptc['2#120'][0] ?? null;
        if ($description !== null && trim($description) !== '') {
            $result['description'] = $decode($description);
        }

        $keywords = array_values(array_filter(array_map(
            $decode,
            $iptc['2#025'] ?? []
        ), static fn (string $keyword): bool => $keyword !== ''));

        if ($keywords !== []) {
            $result['keywords'] = $keywords;
        }

        return $result;
    }

    /**
     * Seed the editable fields from IPTC so a caption written in Lightroom
     * survives the upload. Only ever fills blanks — this runs on a fresh entity
     * today, but keeping it non-destructive means it stays safe if it is ever
     * reused on a re-import.
     *
     * @param array{title?: string, description?: string, keywords?: list<string>} $iptc
     */
    private function applyIptc(Media $media, array $iptc): void
    {
        if (($iptc['title'] ?? '') !== '' && ($media->getTitle() ?? '') === '') {
            $media->setTitle($iptc['title']);
        }

        if (($iptc['description'] ?? '') !== '') {
            if (($media->getCaption() ?? '') === '') {
                $media->setCaption($iptc['description']);
            }

            // A description doubles as a reasonable starting alt text; the
            // editor can always overwrite it.
            if (($media->getAltText() ?? '') === '') {
                $media->setAltText($iptc['description']);
            }
        }
    }

    /**
     * Copy the upload aside before anything rewrites it.
     *
     * Mirrors the master's own subfolder and filename under `uploads/originals`
     * so the pair is obvious on disk and needs no lookup to match up.
     *
     * Returns the absolute path of the copy, or null when it could not be
     * made — a failure here must not lose the upload, so the caller carries on
     * and simply records no original.
     */
    private function preserveOriginal(string $filePath, string $subDir, string $filename): ?string
    {
        $originalsDir = str_replace('/tus/', '/originals/', $subDir);

        if ($originalsDir === $subDir) {
            // The masters directory is not where it was assumed to be; better
            // to keep no original than to write one somewhere unexpected.
            return null;
        }

        if (!is_dir($originalsDir) && !mkdir($originalsDir, 0755, true) && !is_dir($originalsDir)) {
            return null;
        }

        $target = $originalsDir . '/' . $filename;

        return @copy($filePath, $target) ? $target : null;
    }

    /**
     * Downscale an uploaded image in place so its longest side does not exceed
     * MAX_DIMENSION, overwriting the master. The untouched upload is preserved
     * separately — see {@see preserveOriginal()}.
     *
     * Uses the appkit Darkroom engine, which resizes preserving aspect ratio
     * (never upscaling), keeps transparency and auto-orients via SimpleImage.
     *
     * Returns true when the file was resized and rewritten.
     */
    private function downscaleInPlace(string $path): bool
    {
        $size = @getimagesize($path);
        if ($size === false) {
            return false;
        }

        [$width, $height] = $size;
        if (max($width, $height) <= self::MAX_DIMENSION) {
            return false;
        }

        try {
            // width == height box → longest side is capped to MAX_DIMENSION,
            // aspect ratio preserved, no upscaling (force defaults to false).
            $this->darkroom->process($path, [
                'width' => self::MAX_DIMENSION,
                'height' => self::MAX_DIMENSION,
            ]);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Bake a non-normal EXIF orientation into the pixels and drop the flag, so the
     * stored master is physically upright. Keeps width/height, the GD thumbhash and
     * darkroom crops consistent instead of trusting downstream EXIF handling.
     *
     * Only JPEGs carry an orientation flag; already-upright images and other formats
     * are left untouched. The GD re-encode strips the EXIF block, so there is no flag
     * left to double-rotate. Returns true when the file was rewritten.
     */
    private function orientInPlace(string $path, string $mimeType): bool
    {
        if (!in_array($mimeType, ['image/jpeg', 'image/jpg'], true)) {
            return false;
        }

        if (!function_exists('exif_read_data')) {
            return false;
        }

        $exif = @exif_read_data($path);
        if (($exif['Orientation'] ?? 1) <= 1) {
            return false;
        }

        try {
            (new SimpleImage())->fromFile($path)->autoOrient()->toFile($path, $mimeType, 90);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
