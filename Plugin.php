<?php
/**
 * Justified Gallery Plugin for Typecho
 * Typecho 1.3 瀑布流插件
 * 
 * @package JustifiedGallery
 * @author Lan-Feng
 * @version 1.4.1
 * @link https://lonecho.com
 */
class JustifiedGallery_Plugin implements Typecho_Plugin_Interface
{
    /**
     * CDN地址配置（可在此处添加更多CDN源）
     */
    private static $cdnSources = [
        // 国内CDN（推荐）
        'jsdelivr' => [
            'jquery' => 'https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js',
            'justifiedGallery_css' => 'https://cdn.jsdelivr.net/npm/justifiedGallery@3.8.1/dist/css/justifiedGallery.min.css',
            'justifiedGallery_js' => 'https://cdn.jsdelivr.net/npm/justifiedGallery@3.8.1/dist/js/jquery.justifiedGallery.min.js',
            'lazyload' => 'https://cdn.jsdelivr.net/npm/jquery-lazyload@1.9.7/jquery.lazyload.min.js'
        ],
        'bootcdn' => [
            'jquery' => 'https://cdn.bootcdn.net/ajax/libs/jquery/3.6.0/jquery.min.js',
            'justifiedGallery_css' => 'https://cdn.bootcdn.net/ajax/libs/justifiedGallery/3.8.1/css/justifiedGallery.min.css',
            'justifiedGallery_js' => 'https://cdn.bootcdn.net/ajax/libs/justifiedGallery/3.8.1/js/jquery.justifiedGallery.min.js',
            'lazyload' => 'https://cdn.bootcdn.net/ajax/libs/jquery.lazyload/1.9.7/jquery.lazyload.min.js'
        ],
        // 国际CDN
        'cdnjs' => [
            'jquery' => 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js',
            'justifiedGallery_css' => 'https://cdnjs.cloudflare.com/ajax/libs/justifiedGallery/3.8.1/css/justifiedGallery.min.css',
            'justifiedGallery_js' => 'https://cdnjs.cloudflare.com/ajax/libs/justifiedGallery/3.8.1/js/jquery.justifiedGallery.min.js',
            'lazyload' => 'https://cdnjs.cloudflare.com/ajax/libs/jquery.lazyload/1.9.7/jquery.lazyload.min.js'
        ],
        // 本地资源（fallback）
        'local' => [
            'jquery' => '', // 动态生成
            'justifiedGallery_css' => '',
            'justifiedGallery_js' => '',
            'lazyload' => ''
        ]
    ];

    /**
     * 激活插件
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Archive')->header = array('JustifiedGallery_Plugin', 'header');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('JustifiedGallery_Plugin', 'footer');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('JustifiedGallery_Plugin', 'parseGallery');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('JustifiedGallery_Plugin', 'parseGallery');
        
        return _t('瀑布流图库插件已激活，支持CDN加速和懒加载功能');
    }

    /**
     * 禁用插件
     */
    public static function deactivate()
    {
        return _t('瀑布流图库插件已禁用');
    }

    /**
     * 插件配置面板（简化版）
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // CDN配置
        $cdnConfig = new Typecho_Widget_Helper_Form_Element_Radio(
            'cdn_source',
            [
                'none' => _t('不使用CDN（本地资源）'),
                'jsdelivr' => _t('jsDelivr CDN（国内推荐）'),
                'bootcdn' => _t('BootCDN（国内备选）'),
                'cdnjs' => _t('cdnjs（国际CDN）'),
                'custom' => _t('自定义CDN地址')
            ],
            'jsdelivr',
            _t('CDN加速源'),
            _t('选择CDN加速源可大幅提升国内访问速度')
        );
        $form->addInput($cdnConfig);

        // 自定义CDN地址（当选择custom时显示）
        $customCdn = new Typecho_Widget_Helper_Form_Element_Text(
            'custom_cdn_jquery',
            null,
            'https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js',
            _t('自定义jQuery CDN地址'),
            _t('自定义jQuery库的CDN地址')
        );
        $form->addInput($customCdn);

        $customCdnCss = new Typecho_Widget_Helper_Form_Element_Text(
            'custom_cdn_css',
            null,
            'https://cdn.jsdelivr.net/npm/justifiedGallery@3.8.1/dist/css/justifiedGallery.min.css',
            _t('自定义CSS CDN地址'),
            _t('自定义JustifiedGallery CSS的CDN地址')
        );
        $form->addInput($customCdnCss);

        $customCdnJs = new Typecho_Widget_Helper_Form_Element_Text(
            'custom_cdn_js',
            null,
            'https://cdn.jsdelivr.net/npm/justifiedGallery@3.8.1/dist/js/jquery.justifiedGallery.min.js',
            _t('自定义JS CDN地址'),
            _t('自定义JustifiedGallery JS的CDN地址')
        );
        $form->addInput($customCdnJs);

        $customCdnLazyload = new Typecho_Widget_Helper_Form_Element_Text(
            'custom_cdn_lazyload',
            null,
            'https://cdn.jsdelivr.net/npm/jquery-lazyload@1.9.7/jquery.lazyload.min.js',
            _t('自定义懒加载JS CDN地址'),
            _t('自定义jQuery LazyLoad插件的CDN地址')
        );
        $form->addInput($customCdnLazyload);

        // CDN回退配置
        $cdnFallback = new Typecho_Widget_Helper_Form_Element_Radio(
            'cdn_fallback',
            [
                '1' => _t('启用'),
                '0' => _t('禁用')
            ],
            '1',
            _t('CDN回退机制'),
            _t('当CDN加载失败时自动切换到本地资源')
        );
        $form->addInput($cdnFallback);

        // 懒加载配置（简化版）
        $lazyloadEnable = new Typecho_Widget_Helper_Form_Element_Radio(
            'lazyload_enable',
            [
                '1' => _t('启用'),
                '0' => _t('禁用')
            ],
            '1',
            _t('启用懒加载'),
            _t('启用后，图片会在滚动到可视区域时才加载，显著提升页面加载速度')
        );
        $form->addInput($lazyloadEnable);

        $lazyloadThreshold = new Typecho_Widget_Helper_Form_Element_Text(
            'lazyload_threshold',
            null,
            '200',
            _t('加载阈值（像素）'),
            _t('图片距离可视区域多少像素时开始加载，数值越大加载越早')
        );
        $lazyloadThreshold->addRule('isInteger', _t('必须为整数'));
        $form->addInput($lazyloadThreshold);

        $lazyloadEffect = new Typecho_Widget_Helper_Form_Element_Select(
            'lazyload_effect',
            [
                'fadeIn' => '淡入效果',
                'show' => '直接显示',
                'none' => '无效果'
            ],
            'fadeIn',
            _t('加载效果'),
            _t('图片加载时的显示效果')
        );
        $form->addInput($lazyloadEffect);

        // 图库布局配置
        // 图片间距
        $margin = new Typecho_Widget_Helper_Form_Element_Text(
            'margin',
            null,
            '10',
            _t('图片间距'),
            _t('图片之间的像素间距')
        );
        $margin->addRule('isInteger', _t('必须为整数'));
        $form->addInput($margin);

        // 行高设置
        $rowHeight = new Typecho_Widget_Helper_Form_Element_Text(
            'rowHeight',
            null,
            '200',
            _t('行高'),
            _t('每行的目标高度')
        );
        $rowHeight->addRule('isInteger', _t('必须为整数'));
        $form->addInput($rowHeight);

        // 最后一行对齐
        $lastRow = new Typecho_Widget_Helper_Form_Element_Select(
            'lastRow',
            [
                'justify' => '两端对齐',
                'nojustify' => '左对齐',
                'center' => '居中',
                'right' => '右对齐',
                'hide' => '隐藏'
            ],
            'nojustify',
            _t('最后一行对齐'),
            _t('最后一行图片的对齐方式')
        );
        $form->addInput($lastRow);

        // 图片标题显示
        $captions = new Typecho_Widget_Helper_Form_Element_Radio(
            'captions',
            [
                '1' => '显示',
                '0' => '隐藏'
            ],
            '1',
            _t('显示图片标题'),
            _t('是否显示图片的alt属性作为标题')
        );
        $form->addInput($captions);

        // 边框宽度
        $border = new Typecho_Widget_Helper_Form_Element_Text(
            'border',
            null,
            '0',
            _t('边框宽度'),
            _t('图片边框宽度像素')
        );
        $border->addRule('isInteger', _t('必须为整数'));
        $form->addInput($border);
    }

    /**
     * 个人配置面板
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    /**
     * 获取资源URL
     */
    private static function getResourceUrl($type)
    {
        $options = Helper::options();
        $settings = $options->plugin('JustifiedGallery');
        $pluginUrl = $options->pluginUrl . '/JustifiedGallery/';
        
        $cdnSource = isset($settings->cdn_source) ? $settings->cdn_source : 'jsdelivr';
        
        // 生成本地资源URL
        self::$cdnSources['local'] = [
            'jquery' => $pluginUrl . 'assets/jquery.min.js',
            'justifiedGallery_css' => $pluginUrl . 'assets/justifiedGallery.min.css',
            'justifiedGallery_js' => $pluginUrl . 'assets/jquery.justifiedGallery.min.js',
            'lazyload' => $pluginUrl . 'assets/jquery.lazyload.min.js'
        ];
        
        if ($cdnSource === 'custom') {
            // 自定义CDN地址
            $customUrls = [
                'jquery' => isset($settings->custom_cdn_jquery) ? $settings->custom_cdn_jquery : self::$cdnSources['jsdelivr']['jquery'],
                'justifiedGallery_css' => isset($settings->custom_cdn_css) ? $settings->custom_cdn_css : self::$cdnSources['jsdelivr']['justifiedGallery_css'],
                'justifiedGallery_js' => isset($settings->custom_cdn_js) ? $settings->custom_cdn_js : self::$cdnSources['jsdelivr']['justifiedGallery_js'],
                'lazyload' => isset($settings->custom_cdn_lazyload) ? $settings->custom_cdn_lazyload : self::$cdnSources['jsdelivr']['lazyload']
            ];
            
            return $customUrls[$type] ?? '';
        }
        
        if ($cdnSource === 'none') {
            return self::$cdnSources['local'][$type];
        }
        
        // 使用预设CDN
        return isset(self::$cdnSources[$cdnSource][$type]) ? 
               self::$cdnSources[$cdnSource][$type] : 
               self::$cdnSources['jsdelivr'][$type];
    }

    /**
     * 头部输出CSS
     */
    public static function header()
    {
        $cssUrl = self::getResourceUrl('justifiedGallery_css');
        
        // 输出CSS
        echo '<link rel="stylesheet" href="' . $cssUrl . '" />' . "\n";
        
        // 内联CSS
        echo '<style>
        .justified-gallery {
            margin: 20px 0;
            width: 100%;
            min-height: 100px;
        }
        .justified-gallery > a {
            display: inline-block;
            position: absolute;
            background: #f8f9fa;
            transition: opacity 0.3s ease;
        }
        .justified-gallery > a:hover {
            opacity: 0.9;
        }
        .jg-caption {
            display: none;
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 8px;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            text-align: center;
            font-size: 14px;
        }
        /* 懒加载样式 */
        .justified-gallery img.lazy {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .justified-gallery img.loaded {
            opacity: 1;
        }
        </style>' . "\n";
    }

    /**
     * 底部输出JS - 简化版懒加载
     */
    public static function footer()
    {
        $options = Helper::options();
        $settings = $options->plugin('JustifiedGallery');
        $pluginUrl = $options->pluginUrl . '/JustifiedGallery/';
        
        // 获取配置
        $cdnSource = isset($settings->cdn_source) ? $settings->cdn_source : 'jsdelivr';
        $useFallback = isset($settings->cdn_fallback) ? ($settings->cdn_fallback == '1') : true;
        $useCdn = ($cdnSource !== 'none');
        $lazyloadEnable = isset($settings->lazyload_enable) ? ($settings->lazyload_enable == '1') : true;
        $lazyloadThreshold = isset($settings->lazyload_threshold) ? intval($settings->lazyload_threshold) : 200;
        $lazyloadEffect = isset($settings->lazyload_effect) ? $settings->lazyload_effect : 'fadeIn';
        $rowHeight = isset($settings->rowHeight) ? intval($settings->rowHeight) : 200;
        $margin = isset($settings->margin) ? intval($settings->margin) : 10;
        $lastRow = isset($settings->lastRow) ? $settings->lastRow : 'nojustify';
        $captions = isset($settings->captions) ? $settings->captions : '1';
        $border = isset($settings->border) ? intval($settings->border) : 0;
        
        // 获取资源URL
        $jqueryUrl = self::getResourceUrl('jquery');
        $justifiedGalleryUrl = self::getResourceUrl('justifiedGallery_js');
        $lazyloadUrl = $lazyloadEnable ? self::getResourceUrl('lazyload') : '';
        $localJqueryUrl = $pluginUrl . 'assets/jquery.min.js';
        $localJustifiedGalleryUrl = $pluginUrl . 'assets/jquery.justifiedGallery.min.js';
        $localLazyloadUrl = $pluginUrl . 'assets/jquery.lazyload.min.js';
        
        echo '<script>
        (function() {
            "use strict";
            
            // 配置参数
            var config = {
                rowHeight: ' . $rowHeight . ',
                margins: ' . $margin . ',
                lastRow: "' . $lastRow . '",
                captions: ' . ($captions == '1' ? 'true' : 'false') . ',
                border: ' . $border . ',
                cssAnimation: true,
                waitThumbnailsLoad: false
            };
            
            // 懒加载配置
            var lazyloadConfig = {
                enable: ' . ($lazyloadEnable ? 'true' : 'false') . ',
                threshold: ' . $lazyloadThreshold . ',
                effect: "' . $lazyloadEffect . '"
            };
            
            // 资源加载函数
            function loadScript(src, callback, fallbackSrc) {
                var script = document.createElement("script");
                script.src = src;
                script.onload = function() {
                    callback(true);
                };
                script.onerror = function() {
                    if (fallbackSrc) {
                        console.warn("CDN加载失败，切换到本地资源: " + src);
                        loadScript(fallbackSrc, callback, null);
                    } else {
                        callback(false);
                    }
                };
                document.head.appendChild(script);
            }
            
            // 初始化懒加载函数（简化版）
            function initLazyLoad() {
                if (!lazyloadConfig.enable) {
                    return false;
                }
                
                if (typeof jQuery === "undefined" || typeof jQuery.fn.lazyload === "undefined") {
                    console.warn("LazyLoad插件未加载，跳过懒加载初始化");
                    return false;
                }
                
                jQuery(".justified-gallery img.lazy").lazyload({
                    threshold: lazyloadConfig.threshold,
                    effect: lazyloadConfig.effect,
                    event: "scroll",
                    skip_invisible: false,
                    load: function() {
                        // 图片加载完成后的回调
                        jQuery(this).addClass("loaded");
                        
                        // 重新计算布局
                        var $gallery = jQuery(this).closest(".justified-gallery");
                        if ($gallery.length && typeof $gallery.data("justifiedGallery") !== "undefined") {
                            setTimeout(function() {
                                try {
                                    $gallery.justifiedGallery("norewind");
                                } catch (e) {
                                    console.warn("重新布局失败:", e);
                                }
                            }, 100);
                        }
                    }
                });
                
                return true;
            }
            
            // 初始化图库函数
            function initJustifiedGallery() {
                if (typeof jQuery === "undefined") {
                    console.error("jQuery未加载，图库初始化失败");
                    return;
                }
                
                if (typeof jQuery.fn.justifiedGallery === "undefined") {
                    console.error("JustifiedGallery插件未加载");
                    return;
                }
                
                var galleries = jQuery(".justified-gallery");
                if (galleries.length === 0) {
                    return;
                }
                
                galleries.each(function() {
                    try {
                        jQuery(this).justifiedGallery(config);
                    } catch (e) {
                        console.error("图库初始化失败:", e);
                    }
                });
                
                // 如果启用了懒加载，初始化懒加载
                if (lazyloadConfig.enable) {
                    setTimeout(function() {
                        initLazyLoad();
                    }, 500);
                }
                
                // 窗口调整大小时重新布局
                var resizeTimer;
                jQuery(window).on("resize", function() {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(function() {
                        galleries.justifiedGallery("norewind");
                    }, 250);
                });
            }
            
            // 加载懒加载插件
            function loadLazyLoadPlugin(callback) {
                if (!lazyloadConfig.enable) {
                    callback(true);
                    return;
                }
                
                if (typeof jQuery.fn.lazyload !== "undefined") {
                    callback(true);
                    return;
                }
                
                var fallback = ' . ($useFallback && $useCdn ? 'true' : 'false') . ';
                loadScript(
                    "' . $lazyloadUrl . '",
                    function(success) {
                        if (!success && fallback) {
                            loadScript("' . $localLazyloadUrl . '", function(fallbackSuccess) {
                                callback(fallbackSuccess);
                            }, null);
                        } else {
                            callback(success);
                        }
                    },
                    fallback ? "' . $localLazyloadUrl . '" : null
                );
            }
            
            // 主加载逻辑
            function main() {
                if (typeof jQuery !== "undefined") {
                    // jQuery已存在
                    loadJustifiedGalleryPlugin();
                } else {
                    // 加载jQuery
                    var fallback = ' . ($useFallback && $useCdn ? 'true' : 'false') . ';
                    loadScript(
                        "' . $jqueryUrl . '",
                        function(success) {
                            if (!success && fallback) {
                                loadScript("' . $localJqueryUrl . '", function() {
                                    loadJustifiedGalleryPlugin();
                                }, null);
                            } else {
                                loadJustifiedGalleryPlugin();
                            }
                        },
                        fallback ? "' . $localJqueryUrl . '" : null
                    );
                }
            }
            
            function loadJustifiedGalleryPlugin() {
                if (typeof jQuery.fn.justifiedGallery !== "undefined") {
                    // JustifiedGallery已存在
                    loadLazyLoadPlugin(function() {
                        initJustifiedGallery();
                    });
                } else {
                    // 加载JustifiedGallery
                    var fallback = ' . ($useFallback && $useCdn ? 'true' : 'false') . ';
                    loadScript(
                        "' . $justifiedGalleryUrl . '",
                        function(success) {
                            if (!success && fallback) {
                                loadScript("' . $localJustifiedGalleryUrl . '", function() {
                                    loadLazyLoadPlugin(function() {
                                        initJustifiedGallery();
                                    });
                                }, null);
                            } else {
                                loadLazyLoadPlugin(function() {
                                    initJustifiedGallery();
                                });
                            }
                        },
                        fallback ? "' . $localJustifiedGalleryUrl . '" : null
                    );
                }
            }
            
            // 开始执行
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", main);
            } else {
                main();
            }
        })();
        </script>' . "\n";
    }

    /**
     * 解析文章内容中的图库（简化版懒加载）
     */
    public static function parseGallery($content, $widget, $lastResult)
    {
        $content = empty($lastResult) ? $content : $lastResult;
        
        if ($widget instanceof Widget_Archive) {
            // 匹配 [jpg] 标签
            $pattern = '/\[jpg\](.*?)\[\/jpg\]/s';
            $content = preg_replace_callback($pattern, array('JustifiedGallery_Plugin', 'renderGallery'), $content);
        }
        
        return $content;
    }

    /**
     * 渲染图库（简化版懒加载）
     */
    public static function renderGallery($matches)
    {
        if (empty($matches[1])) {
            return '<!-- JustifiedGallery: 空内容 -->';
        }
        
        try {
            $options = Helper::options();
            $settings = $options->plugin('JustifiedGallery');
            
            // 获取配置
            $lazyloadEnable = isset($settings->lazyload_enable) ? ($settings->lazyload_enable == '1') : true;
            
            $content = trim($matches[1]);
            $images = array();
            
            // 1. 匹配Markdown图片
            if (preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', $content, $mdMatches, PREG_SET_ORDER)) {
                foreach ($mdMatches as $match) {
                    $url = trim($match[2]);
                    $alt = !empty($match[1]) ? trim($match[1]) : '图片';
                    
                    if (!empty($url)) {
                        $images[] = array(
                            'url' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                            'alt' => htmlspecialchars($alt, ENT_QUOTES, 'UTF-8')
                        );
                    }
                }
            }
            
            // 2. 如果没有Markdown图片，尝试HTML图片
            if (empty($images) && preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $htmlMatches, PREG_SET_ORDER)) {
                foreach ($htmlMatches as $match) {
                    $url = trim($match[1]);
                    if (empty($url)) continue;
                    
                    $alt = '图片';
                    if (preg_match('/alt=["\']([^"\']*)["\']/i', $match[0], $altMatches)) {
                        $alt = !empty($altMatches[1]) ? trim($altMatches[1]) : '图片';
                    }
                    
                    $images[] = array(
                        'url' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                        'alt' => htmlspecialchars($alt, ENT_QUOTES, 'UTF-8')
                    );
                }
            }
            
            // 3. 最后尝试纯URL
            if (empty($images)) {
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    // 简单的图片URL检查
                    $imageExts = array('.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.svg');
                    foreach ($imageExts as $ext) {
                        if (stripos($line, $ext) !== false && (strpos($line, 'http://') === 0 || strpos($line, 'https://') === 0 || strpos($line, '/') === 0)) {
                            $images[] = array(
                                'url' => htmlspecialchars($line, ENT_QUOTES, 'UTF-8'),
                                'alt' => '图片'
                            );
                            break;
                        }
                    }
                }
            }
            
            if (empty($images)) {
                return '<!-- JustifiedGallery: 未找到图片 -->';
            }
            
            // 构建图库HTML（简化懒加载）
            $html = '<div class="justified-gallery">' . "\n";
            foreach ($images as $image) {
                $html .= '    <a href="' . $image['url'] . '" title="' . $image['alt'] . '">' . "\n";
                
                if ($lazyloadEnable) {
                    // 使用懒加载（简化版）
                    $html .= '        <img class="lazy" data-original="' . $image['url'] . '" src="' . $image['url'] . '" alt="' . $image['alt'] . '" />' . "\n";
                } else {
                    // 不使用懒加载
                    $html .= '        <img src="' . $image['url'] . '" alt="' . $image['alt'] . '" />' . "\n";
                }
                
                $html .= '    </a>' . "\n";
            }
            $html .= '</div>' . "\n";
            
            return $html;
            
        } catch (Exception $e) {
            return '<!-- JustifiedGallery错误 -->';
        }
    }
}