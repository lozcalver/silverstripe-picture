<?php

namespace Kinglozzer\SilverstripePicture;

use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\AssetContainer;
use SilverStripe\Model\ModelData;
use SilverStripe\View\HTML;

class Img extends ModelData
{
    use SrcsetProviderTrait {
        __construct as __srcsetProviderConstruct;
        __call as __srcsetProviderCall;
    }

    /**
     * The default image for this <picture> element, to be rendered as a nested <img /> tag
     */
    protected Image $defaultImage;

    /**
     * A store of any manipulations performed on the default image
     */
    protected array $defaultImageManipulations = [];

    public function __construct(Image $sourceImage, array $config)
    {
        parent::__construct();
        $this->__srcsetProviderConstruct($sourceImage, $config);
        $this->defaultImage = $sourceImage;
    }

    /**
     * For the default Img tag, we ensure that any requested manipulation is also called against the
     * default image that will become the "src", not just the srcset attribute image candidates
     */
    public function __call($method, $arguments)
    {
        $clone = $this->__srcsetProviderCall($method, $arguments);
        $clone->defaultImage = $clone->defaultImage->$method(...$arguments);
        $clone->defaultImageManipulations[] = [
            'method' => $method,
            'arguments' => $arguments
        ];

        return $clone;
    }

    protected function getDefaultAttributes(): array
    {
        $imageForSrc = $this->getImageForSrc();
        $attributes = [
            'alt' => $this->sourceImage->getTitle(),
            'src' => $imageForSrc->getURL(),
            'srcset' => $this->getImageCandidatesString()
        ];

        if ($this->sourceImage->IsLazyLoaded()) {
            $attributes['loading'] = 'lazy';
        }

        $this->extend('updateDefaultAttributes', $attributes);

        return $attributes;
    }

    public function getDefaultImage(): Image
    {
        return $this->defaultImage;
    }

    public function getImageForSrc(): AssetContainer
    {
        return $this->imageCandidates[0]['image'] ?? $this->getDefaultImage();
    }

    /**
     * For the default <img> tag, we re-use the existing Silverstripe Image object and inject a srcset attribute
     */
    public function forTemplate(): string
    {
        $this->extend('onBeforeRender');

        $attributes = array_merge($this->sourceImage->getAttributes(), $this->getDefaultAttributes());

        $imageForSrc = $this->getImageForSrc();
        $attributes['width'] = $imageForSrc->getWidth();
        $attributes['height'] = $imageForSrc->getHeight();

        $this->extend('updateAttributes', $attributes);

        if (!isset($attributes['src'])) {
            return '';
        }

        $html = HTML::createTag('img', $attributes);

        $this->extend('updateForTemplateHtml', $html, $attributes);

        return $html;
    }
}
