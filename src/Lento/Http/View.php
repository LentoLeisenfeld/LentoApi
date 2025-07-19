<?php

namespace Lento\Http;

use Lento\Cache;

class View {
    protected $viewName;
    protected $model;
    protected $partial;

    public function __construct($viewName, $model = null, $partial = false) {
        $this->viewName = $viewName;
        $this->model = $model;
        $this->partial = $partial;
    }

    public function render() {
        $model = $this->model;
        $viewFile = Cache::getDirectory() . "/{$this->viewName}.php";
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View '{$viewFile}' not found.");
        }

        ob_start();
        include $viewFile;
        $content = ob_get_clean();

        // Now render layout, passing $content and $model
        $layoutFile = Cache::getDirectory() . "/_layout.php";
        if (!$this->partial && file_exists($layoutFile)) {
            ob_start();
            include $layoutFile; // This file will have access to $content and $model
            return ob_get_clean();
        } else {
            // No layout: return raw content
            return $content;
        }
    }
}