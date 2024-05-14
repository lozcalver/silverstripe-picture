<?php

namespace Kinglozzer\SilverstripePicture;

use Exception;
use InvalidArgumentException;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\DBFile;
use SilverStripe\Core\Injector\Factory;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\ViewableData;

class Picture extends ViewableData
{
    /**
     * A config array of available styles to be called in templates
     */
    private static array $styles = [];

    /**
     * The underlying Silverstripe image
     */
    protected DBFile $image;

    /**
     * The requested picture style
     */
    protected string $style;

    /**
     * The config settings for the requested picture style
     */
    protected ?array $styleConfig;

    public function __construct(DBFile $image, string $style)
    {
        parent::__construct();

        $this->image = $image;
        $this->style = $style;

        $styles = array_change_key_case($this->config()->get('styles'), CASE_LOWER);
        $this->styleConfig = $styles[$this->style];
    }

    public function forTemplate(): DBHTMLText
    {
        return $this->renderWith(__CLASS__);
    }

    public function getSourceImage(): DBFile
    {
        return $this->image;
    }

    /**
     * @throws Exception
     */
    public function getDefaultImage(): Img
    {
        $defaultConfig = $this->styleConfig['default'] ?? [];
        if (!$defaultConfig) {
            throw new Exception("No default config set for style “{$this->style}”");
        }

        return Img::create($this->image, $defaultConfig);
    }

    /**
     * @throws Exception
     */
    public function getSources(): ArrayList
    {
        $sources = ArrayList::create();
        $sourcesConfig = $this->styleConfig['sources'] ?? [];
        if (empty($sourcesConfig)) {
            return $sources;
        }

        foreach ($sourcesConfig as $media => $sourceConfig) {
            if (!is_array($sourceConfig)) {
                throw new InvalidArgumentException("Invalid source config for style “{$this->style}”");
            }

            if (is_numeric($media)) {
                $media = $sourceConfig['media'] ?? '';
            }
            $source = Source::create($this->image, $media, $sourceConfig);

            $sources->push($source);
        }

        return $sources;
    }
}
