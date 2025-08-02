<?php

namespace Lento\Http;

use RuntimeException;

use Lento\Renderer;

class View
{
    /**
     * Undocumented variable
     *
     * @var [type]
     */
    protected $view;

    /**
     * Undocumented variable
     *
     * @var [type]
     */
    protected $model;

    /**
     * Undocumented variable
     *
     * @var [type]
     */
    protected $partial;

    /**
     * Undocumented variable
     *
     * @var [type]
     */
    protected $layout;

    /**
     * Undocumented variable
     *
     * @var array
     */
    protected $sections = [];

    /**
     * Undocumented variable
     *
     * @var [type]
     */
    protected $currentSection = null;

    /**
     * Undocumented variable
     *
     * @var integer
     */
    protected $sectionBufferLevel = 0;

    /**
     * Undocumented function
     *
     * @param [type] $view
     * @param [type] $model
     * @param boolean $partial
     * @param [type] $layout
     */
    public function __construct($view, $model = null, $partial = false, $layout = null)
    {
        $this->view = $view;
        $this->model = $model;
        $this->partial = $partial;
        $this->layout = $layout ?: Renderer::$options->layout ?: null;
    }

    /**
     * Undocumented function
     *
     * @param [type] $name
     * @return void
     */
    public function startSection($name): void
    {
        if ($this->currentSection !== null) {
            throw new RuntimeException("A section is already started: '{$this->currentSection}'");
        }
        $this->currentSection = $name;
        $this->sectionBufferLevel = ob_get_level();
        ob_start();
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new RuntimeException("No section is currently started.");
        }
        $content = ob_get_clean();
        $this->sections[$this->currentSection] = $content;
        $this->currentSection = null;
    }

    /**
     * Undocumented function
     *
     * @param [type] $name
     * @param boolean $required
     * @return bool|string
     */
    public function section($name, $required = false): bool|string
    {
        if (isset($this->sections[$name])) {
            return $this->sections[$name];
        }
        if ($required) {
            throw new RuntimeException("Section '{$name}' is required but not defined.");
        }
        return '';
    }

    /**
     * Undocumented function
     *
     * @return bool|string
     */
    public function render()
    {
        $model = $this->model;
        $viewFile = Renderer::$options->directory . "/{$this->view}.php";
        if (!file_exists($viewFile)) {
            throw new RuntimeException("View '{$viewFile}' not found.");
        }

        // Render view (inside $this context)
        ob_start();
        include $viewFile;
        $content = ob_get_clean();

        $layoutFile = Renderer::$options->directory . '/' . $this->layout . '.php';
        if (!$this->partial && file_exists($layoutFile)) {
            ob_start();
            include $layoutFile;
            return ob_get_clean();
        } else {
            return $content;
        }
    }
}
