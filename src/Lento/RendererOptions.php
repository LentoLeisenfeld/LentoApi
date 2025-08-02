<?php

namespace Lento;

/**
 * Undocumented class
 */
class RendererOptions
{
    /**
     * Undocumented variable
     *
     * @var ?string
     */
    public ?string $directory = null;

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $layout = '_Layout';

    /**
     * Undocumented function
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function toArray(): array
    {
        $result = [];

        if (!empty($this->directory)) {
            $result['directory'] = $this->directory;
        }

        if (!empty($this->layout)) {
            $result['layout'] = $this->layout;
        }

        return $result;
    }
}
