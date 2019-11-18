<?php

declare(strict_types=1);

namespace xiaodi;

use think\App;
use think\helper\Str;
use think\template\exception\TemplateNotFoundException;
use think\contract\TemplateHandlerInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Twig implements TemplateHandlerInterface
{
    private $app;

    // 模板引擎参数
    protected $config = [
        'cache' => '',
        'auto_reload' => true,  //根据文件更新时间，自动更新缓存
        'debug' => true
    ];

    public function __construct(App $app, array $config = [])
    {
        $this->app = $app;

        $this->config = array_merge($this->config, (array) $config);

        if (empty($this->config['cache_path'])) {
            $this->config['cache'] = $app->getRuntimePath() . 'temp' . DIRECTORY_SEPARATOR;
        }

    }

    /**
     * 检测是否存在模板文件
     * @access public
     * @param  string $template 模板文件或者模板规则
     * @return bool
     */
    public function exists(string $template): bool
    {
        if ('' == pathinfo($template, PATHINFO_EXTENSION)) {
            // 获取模板文件名
            $template = $this->parseTemplate($template);
        }

        return is_file($this->config['view_path'] . $template);
    }

    /**
     * 渲染模板文件
     * @access public
     * @param  string    $template 模板文件
     * @param  array     $data 模板变量
     * @return void
     */
    public function fetch(string $template, array $data = []): void
    {
        if (empty($this->config['view_path'])) {
            $view = $this->config['view_dir_name'];

            if (is_dir($this->app->getAppPath() . $view)) {
                $path = $this->app->getAppPath() . $view . DIRECTORY_SEPARATOR;
            } else {
                $appName = $this->app->http->getName();
                $path    = $this->app->getRootPath() . $view . DIRECTORY_SEPARATOR . ($appName ? $appName . DIRECTORY_SEPARATOR : '');
            }

            $this->config['view_path'] = $path;
        }

        if ('' == pathinfo($template, PATHINFO_EXTENSION)) {
            // 获取模板文件名
            $template = $this->parseTemplate($template);
        }

        // 模板不存在 抛出异常
        $file = $path . $template;
        if (!is_file($file)) {
            throw new TemplateNotFoundException('template not exists:' . $file, $file);
        }

        $loader = new FilesystemLoader($path);
        $twig = new Environment($loader, $this->config);

        // 记录视图信息
        $this->app['log']
            ->record('[ VIEW ] ' . $template . ' [ ' . var_export(array_keys($data), true) . ' ]');

        echo $twig->render($template, $data);
    }

    /**
     * 渲染模板内容
     * @access public
     * @param  string    $content 模板内容
     * @param  array     $data 模板变量
     * @return void
     */
    public function display(string $content, array $data = []): void
    {
        // TODO
    }

    /**
     * 自动定位模板文件
     * @access private
     * @param  string $template 模板文件规则
     * @return string
     */
    private function parseTemplate(string $template): string
    {
        // 分析模板文件规则
        $request = $this->app['request'];

        // 获取视图根目录
        if (strpos($template, '@')) {
            // 跨模块调用
            list($app, $template) = explode('@', $template);
        }

        $depr = $this->config['view_depr'];

        if (0 !== strpos($template, '/')) {
            $template   = str_replace(['/', ':'], $depr, $template);
            $controller = $request->controller();

            if (strpos($controller, '.')) {
                $pos        = strrpos($controller, '.');
                $controller = substr($controller, 0, $pos) . '.' . Str::snake(substr($controller, $pos + 1));
            } else {
                $controller = Str::snake($controller);
            }

            if ($controller) {
                if ('' == $template) {
                    // 如果模板文件名为空 按照默认模板渲染规则定位
                    if (2 == $this->config['auto_rule']) {
                        $template = $request->action(true);
                    } elseif (3 == $this->config['auto_rule']) {
                        $template = $request->action();
                    } else {
                        $template = Str::snake($request->action());
                    }

                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . $template;
                } elseif (false === strpos($template, $depr)) {
                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . $template;
                }
            }
        } else {
            $template = str_replace(['/', ':'], $depr, substr($template, 1));
        }

        return ltrim($template, '/') . '.twig';
    }

    /**
     * 配置模板引擎
     * @access private
     * @param  array  $config 参数
     * @return void
     */
    public function config(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 获取模板引擎配置
     * @access public
     * @param  string  $name 参数名
     * @return void
     */
    public function getConfig(string $name)
    {
        return $this->config[$name];
    }
}
