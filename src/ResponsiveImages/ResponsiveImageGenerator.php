<?php

namespace Spatie\MediaLibrary\ResponsiveImages;

use Spatie\MediaLibrary\Media;
use Spatie\MediaLibrary\Filesystem\Filesystem;
use Spatie\MediaLibrary\Helpers\TemporaryDirectory;
use Spatie\MediaLibrary\PathGenerator\PathGenerator;
use Spatie\MediaLibrary\ResponsiveImages\WidthCalculator\WidthCalculator;
use Spatie\Image\Image;
use Spatie\MediaLibrary\PathGenerator\PathGeneratorFactory;
use Spatie\TemporaryDirectory\TemporaryDirectory as BaseTemporaryDirectory;
use Spatie\MediaLibrary\Conversion\Conversion;
use Spatie\MediaLibrary\ResponsiveImages\ResponsiveImage;

class ResponsiveImageGenerator
{
    /** \Spatie\MediaLibrary\Filesystem\Filesystem */
    protected $filesystem;

    /** \Spatie\MediaLibrary\ResponsiveImages\WidthCalculator\WidthCalculator */
    protected $widthCalculator;

    public function __construct(
        Filesystem $filesystem,
        WidthCalculator $widthCalculator
    ) {
        $this->filesystem = $filesystem;

        $this->widthCalculator = $widthCalculator;
    }

    public function generateResponsiveImages(Media $media)
    {
        $temporaryDirectory = TemporaryDirectory::create();
        
        $baseImage = app(Filesystem::class)->copyFromMediaLibrary(
            $media,
            $temporaryDirectory->path(str_random(16).'.'.$media->extension)
        );

        foreach ($this->widthCalculator->calculateWidthsFromFile($baseImage) as $width) {
            $this->generateResponsiveImage($media, $baseImage, 'medialibrary_original', $width, $temporaryDirectory);
        }

        $this->generateTinyJpg($media, $baseImage, 'medialibrary_original', $temporaryDirectory);

        $temporaryDirectory->delete();
    }

    public function generateResponsiveImagesForConversion(Media $media, Conversion $conversion, string $baseImage)
    {
        $temporaryDirectory = TemporaryDirectory::create();

        foreach ($this->widthCalculator->calculateWidthsFromFile($baseImage) as $width) {
            $this->generateResponsiveImage($media, $baseImage, $conversion->getName(), $width, $temporaryDirectory);
        }

        $this->generateTinyJpg($media, $baseImage, $conversion->getName(), $temporaryDirectory);

        $temporaryDirectory->delete();
    }

    public function generateResponsiveImage(
        Media $media,
        string $baseImage,
        string $conversionName,
        int $targetWidth,
        BaseTemporaryDirectory $temporaryDirectory
        ) {
        $responsiveImagePath = $this->appendToFileName($media->file_name, "___{$conversionName}_{$targetWidth}");
   
        $tempDestination = $temporaryDirectory->path($responsiveImagePath);

        Image::load($baseImage)->width($targetWidth)->save($tempDestination);

        $responsiveImageHeight = Image::load($tempDestination)->getHeight();

        $finalImageFileName = $this->appendToFileName($responsiveImagePath, "_{$responsiveImageHeight}");

        $finalResponsiveImagePath = $temporaryDirectory->path($finalImageFileName);

        rename($tempDestination, $finalResponsiveImagePath);

        $this->filesystem->copyToMediaLibrary($finalResponsiveImagePath, $media, 'responsiveImages');

        ResponsiveImage::register($media, $finalImageFileName, $conversionName);
    }

    public function generateTinyJpg(Media $media, string $baseImage, string $conversionName, BaseTemporaryDirectory $temporaryDirectory)
    {
        $tempDestination = $temporaryDirectory->path('tiny.jpg');

        Image::load($baseImage)->width(32)->blur(10)->save($tempDestination);

        ResponsiveImage::registerTinyJpg($media, $tempDestination, $conversionName);
    }

    protected function appendToFileName(string $filePath, string $suffix): string
    {
        $baseName = pathinfo($filePath, PATHINFO_FILENAME);

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
  
        return $baseName . $suffix . '.' . $extension;
    }
}