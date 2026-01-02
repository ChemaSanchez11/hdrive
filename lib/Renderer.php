<?php

namespace renderer;

use Exception;
use Mustache_Autoloader;
use Mustache_Engine;
use Mustache_Loader_FilesystemLoader;

class Renderer
{
    private Mustache_Engine $mustache;
    private array $styles = [];
    private array $js = [];
    private array $params_js = [];
    private string $head = '';
    private string $nav = '';
    private string $footer = '';

    /**
     * Constructor: Inicializa Mustache Engine.
     *
     * @param string|null $templatesPath Ruta base de las plantillas (por defecto ../templates)
     * @throws Exception
     */
    public function __construct(?string $templatesPath = null)
    {
        $this->initialize_mustache($templatesPath);
    }

    /**
     * Agrega archivos de estilos CSS.
     *
     * @param array $styles Lista de rutas CSS.
     * @return $this
     */
    public function setStyles(array $styles): self
    {
        $this->styles = array_merge($this->styles, $styles);
        return $this;
    }

    /**
     * Agrega scripts JS.
     *
     * @param array|string $js Archivos JS o array de archivos.
     * @param array $params_js Parámetros adicionales JS.
     * @return $this
     */
    public function setJS(array|string $js, array $params_js = []): self
    {
        if (is_string($js)) {
            $this->js[] = $js;
        } else {
            $this->js = array_merge($this->js, $js);
        }

        if (!empty($params_js)) {
            $this->params_js = array_merge($this->params_js, $params_js);
        }

        return $this;
    }

    /**
     * Inicializa el motor Mustache con FileSystemLoader.
     *
     * @param string|null $templatesPath
     * @throws Exception
     */
    private function initialize_mustache(?string $templatesPath = null): void
    {
        try {
            require_once __DIR__ . '/mustache/src/Mustache/Autoloader.php';
            Mustache_Autoloader::register();

            $path = $templatesPath ?? __DIR__ . '/../templates';

            $loader = new Mustache_Loader_FilesystemLoader($path);
            $partials = new Mustache_Loader_FilesystemLoader($path);

            $this->mustache = new Mustache_Engine([
                'loader' => $loader,
                'partials_loader' => $partials,
                'escape' => 'htmlspecialchars',
                'helpers' => [
                    'asset' => function($filename) {
                        global $CFG;
                        return $CFG->base_url . 'assets' . $filename;
                    }
                ]
            ]);

        } catch (Exception $e) {
            throw new Exception("No se pudo inicializar Mustache: " . $e->getMessage());
        }
    }

    /**
     * Renderiza el <head> del HTML.
     *
     * @param string $title Título de la página.
     * @return $this
     * @throws Exception
     */
    public function head(string $title): self
    {
        $this->head = $this->render_template('core/head', [
            'title' => $title,
            'styles' => $this->styles,
            'js' => $this->js,
            'params_js' => $this->params_js
        ]);

        return $this;
    }

    /**
     * Renderiza la navegación <nav>.
     *
     * @param array $params Datos para la plantilla.
     * @return $this
     * @throws Exception
     */
    public function nav(array $params = []): self
    {
        $this->nav = $this->render_template('core/nav', $params);
        return $this;
    }

    /**
     * Renderiza una plantilla genérica Mustache.
     *
     * @param string $template Nombre de la plantilla.
     * @param array $data Datos para la plantilla.
     * @return string
     * @throws Exception
     */
    public function render_template(string $template, array $data = []): string
    {
        if (!$this->mustache) {
            throw new Exception("Mustache Engine no está inicializado");
        }

        try {
            return $this->mustache->render($template, $data);
        } catch (Exception $e) {
            throw new Exception("Error renderizando plantilla '$template': " . $e->getMessage());
        }
    }

    /**
     * Renderiza un documento HTML completo.
     *
     * @param string $body Contenido del body.
     * @return string
     * @throws Exception
     */
    public function render_html(string $body): string
    {
        return $this->render_template('core/html', [
            'head' => $this->head,
            'nav' => $this->nav,
            'body' => $body,
            'footer' => $this->footer
        ]);
    }

    /**
     * Define contenido del footer
     *
     * @param string $footer HTML del footer
     * @return $this
     */
    public function setFooter(string $footer): self
    {
        $this->footer = $footer;
        return $this;
    }

    /**
     * Limpia todas las variables internas
     */
    public function reset(): void
    {
        $this->styles = [];
        $this->js = [];
        $this->params_js = [];
        $this->head = '';
        $this->nav = '';
        $this->footer = '';
    }
}
