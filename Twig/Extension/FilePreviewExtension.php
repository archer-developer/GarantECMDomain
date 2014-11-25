<?php
/**
 * Created by PhpStorm.
 * User: yanker
 * Date: 09.09.14
 * Time: 14:30
 */
namespace ITM\FilePreviewBundle\Twig\Extension;

use ITM\FilePreviewBundle\Resolver\PathResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;


class FilePreviewExtension extends \Twig_Extension
{
    private static $pathResolver;
    private static $container;

    private static $icons = [
        "doc" => "word.png", "docx" => "word.png",
        "xls" => "excel.png", "xlsx" => "excel.png",
        "ppt" => "powerpoint.png", "pptx" => "powerpoint.png",
        "pdf" => "pdf.png",
        "psd" => "photoshop.png",
        "avi" => "movie.png", "mp4" => "movie.png",
        "mp3" => "music.png", "wav" => "music.png",
        "zip" => "compressed.png", "rar" => "compressed.png", "7z" => "compressed.png", "gz" => "compressed.png",
        "html" => "html.png", "htm" => "html.png",
        "png" => "image.png", "jpeg" => "image.png", "jpg" => "image.png", "bmp" => "image.png", "ico" => "image.png",
        "txt" => "text.png", "xml" => "text.png",
    ];

    public function __construct(PathResolver $pathResolver, ContainerInterface $container)
    {
        self::$pathResolver = $pathResolver;
        self::$container = $container;
    }

    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('itm_file_url', array($this, 'resolveUrl')),
            new \Twig_SimpleFilter('itm_file_path', array($this, 'resolvePath')),
            new \Twig_SimpleFilter('itm_file_exists', array($this, 'fileExists')),
            new \Twig_SimpleFilter('itm_filesize', array($this, 'readableFilesize')),
            new \Twig_SimpleFilter('itm_file_ico', array($this, 'fileIco')),
        );
    }

    /**
     * Иконка файла
     * @param $entity
     * @param $field
     * @return string
     */
    public function fileIco($entity, $field)
    {
        if ( self::$pathResolver->isExists($entity, $field) ) {
            $file_info = pathinfo(self::$pathResolver->getPath($entity, $field, true));
            return isset(self::$icons[$file_info['extension']]) ? self::$icons[$file_info['extension']] : "fileicon_bg.png";
        } else {
            return "fileicon_bg.png";
        }
    }

    /**
     * Url к файлу
     * @param $entity
     * @param $field
     * @return string
     */
    public static function resolveUrl( $entity, $field )
    {
        return self::$pathResolver->getUrl($entity, $field);
    }

    /**
     * Путь к файлу
     * @param $entity
     * @param $field
     * @return string
     */
    public static function resolvePath( $entity, $field )
    {
        return self::$pathResolver->getPath($entity, $field, true);
    }

    /**
     * Проверка на существование файла
     * @param $entity
     * @param $field
     * @return bool
     */
    public static function fileExists( $entity, $field )
    {
        return self::$pathResolver->isExists($entity, $field);
    }

    /**
     * Размер файл в человека понятном виде
     * @param integer $size
     * @return string
     */
    public function readableFilesize($size)
    {
        if( $size <= 0 ) {
            return '0 KB';
        }

        if( $size === 1 ) {
            return '1 byte';
        }

        $mod = 1024;
        $units = array('bytes', 'KB', 'MB', 'GB', 'TB', 'PB');

        for( $i = 0; $size > $mod && $i < count($units) - 1; ++$i ) {
            $size /= $mod;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    public function getName()
    {
        return 'itm_file_preview_extension';
    }
}

                                                                                                                                                                                                                                                                                                                                                                        