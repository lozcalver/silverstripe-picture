<?php

namespace Kinglozzer\SilverstripePicture;

use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\DBFile;
use SilverStripe\View\HTML;
use SilverStripe\View\ViewableData;

class Source extends ViewableData
{
    use SrcsetProviderTrait {
        __construct as __srcsetProviderConstruct;
    }

    /**
     * The "media" attribute for this source tag
     */
    protected string $media;

    public function __construct(DBFile $sourceImage, string $media, array $config)
    {
        parent::__construct();
        $this->__srcsetProviderConstruct($sourceImage, $config);
        $this->media = $media;
    }

    public function forTemplate(): string
    {
        $this->extend('onBeforeRender');

        $candidates = $this->imageCandidates;
        $lastImageCandidate = end($candidates);
        $firstImageCandidate = reset($candidates);
        $lastImage = $lastImageCandidate['image'] ?? $this->sourceImage;
        $firstImage = $firstImageCandidate['image'] ?? $this->sourceImage;

        $attributes = [
            'media' => $this->media,
            'srcset' => $this->getImageCandidatesString(),
            'type' => $lastImage->getMimeType(),
        ];

        $this->extend('updateAttributes', $attributes);

        $html = HTML::createTag('source', $attributes);

        $this->extend('updateForTemplateHtml', $html, $attributes);

        return $html;
    }
}
