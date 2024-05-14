<?php

namespace Kinglozzer\SilverstripePicture;

use Exception;
use SilverStripe\Assets\Storage\DBFile;
use SilverStripe\Core\Extension;
use InvalidArgumentException;
use SilverStripe\Assets\Conversion\FileConverterException;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Assets\Image;

class ImageExtension extends Extension
{
    /**
     * Defines extra methods for each style registered in config
     */
    public function allMethodNames(): array
    {
        $methods = array_map('strtolower', array_keys(Picture::config()->get('styles')));
        $methods[] = 'retina';
        return $methods;
    }

    /**
     * When a style's method is called, return a picture
     */
    public function __call(string $method, array $args = [])
    {
        if ($method === 'Retina') {
            return $this->Retina(...$args);
        }

        return Picture::create($this->owner, strtolower($method));
    }

    /**
     * Try to generate a retina versions of the image. If no retina version can be generated, return false.
     * @param $factorStr A string representing the factor by which to increase the image dimensions.
     *     Must be a float followed by "x" (e.g. "2x")
     */
    public function Retina(string $factorStr = '2x'): DBFile|false
    {
        if (preg_match('/^(\d+(\.\d+)?)x$/', $factorStr, $matches)) {
            $factor = (float) $matches[1];
        } else {
            throw new InvalidArgumentException(
                'Invalid factor provided to Retina method. Must be a float followed by "x" (e.g. "2x")'
            );
        }
        $original = $this->getOwner();

        $originalWidth = $original->getWidth();
        $originalHeight = $original->getHeight();

        $retina = $this->replayManipulations(
            $original,
            $factor,
            $originalWidth,
            $originalHeight
        );

        if ($retina === false) {
            return false;
        }

        // Force the new image to render at the size of the original image
        $retina = $retina
            ->setAttribute('width', $originalWidth)
            ->setAttribute('height', $originalHeight);

        return $retina;

    }

    /**
     * Replay all the image manipulations but increasing the sizes by the
     * provided factor. As the image manipulation are replayed, the size of the
     * image is compared to the      * target dimension. If resizing the image
     * would lead to a degradation in quality, false is returned.
     *
     * @param $image The image to replay the manipulations on
     * @param $factor The factor by which to increase the image dimensions e.g. 2x
     * @param $targetWidth The width of the original image
     * @param $targetHeight The height of the original image
     */
    private function replayManipulations(
        DBFile|Image $image,
        float $factor,
        int $targetWidth,
        int $targetHeight
    ): DBFile|Image|false
    {
        $variantString = $image->getVariant();

        // We've replayed all the manipulations and have run out of variants
        if (empty($variantString)) {
            return $image;
        }

        // Get version of this image without the last manipulation
        $original = $this->replayManipulations($image->getFailover(), $factor, $targetWidth, $targetHeight);

        // The previous manipulation failed to generate a suitable image
        if ($original === false) {
            return false;
        }

        // Check dimensions of the image to see if it's too small to generate suitable variant.
        if (
            $original->getWidth() < $targetWidth * $factor ||
            $original->getHeight() < $targetHeight * $factor
        ) {
            return false;
        }

        // Parse the last manipulation
        $variantStringList = explode('_', $variantString);
        $lastVariantString = end($variantStringList);
        $variant = $image->variantParts($lastVariantString);
        $method = array_shift($variant);

        // Replay the manipulation but doubling the sizes.
        // Do nothing if the parameter is not an integer
        $arguments = array_map(function ($arg) use ($factor) {
            return is_int($arg) ? intval($arg * $factor) : $arg;
        }, $variant);

        // The variant name for converting between format is different from it's method name
        if ($method == 'ExtRewrite') {
            return $original->Convert($arguments[1]);
        }

        return $original->$method(...$arguments);
    }
}
