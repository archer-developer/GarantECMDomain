<?php
/**
 * Created by PhpStorm.
 * User: archer.developer
 * Date: 31.07.14
 * Time: 20:33
 */

namespace ITM\FilePreviewBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class FileSubscriber implements EventSubscriber
{
    private $container;
    private $config;
    private $files = [];
    private $oldFiles = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->config = $this->container->getParameter('ITMFilePreviewBundleConfiguration');
    }

    public function getSubscribedEvents()
    {
        return [
            Events::prePersist,
            Events::preUpdate,
            Events::postPersist,
            Events::postUpdate,
            Events::postLoad,
        ];
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $this->preUpload($args);
    }

    public function preUpdate(LifecycleEventArgs $args)
    {
        $this->preUpload($args);
    }

    /**
     * Сохраняем начальные значения полей сущностей
     *
     * @param LifecycleEventArgs $args
     */
    public function postLoad(LifecycleEventArgs $args)
    {
        $doctrine = $this->container->get('doctrine');
        $accessor = PropertyAccess::createPropertyAccessor();
        $curEntity = $args->getEntity();

        //@todo Нужно переработать формат настройки конфигурации чтобы убрать лишние уровни
        foreach( $this->config['entities'] as $bundleName => $bundle )
        {
            foreach( $bundle['bundle'] as $entityName => $entity )
            {
                $entityClass = get_class($curEntity);
                // Проверяем принадлежит ли сущность тому же бандлу и классу, что и описанная в конфигурации
                if( $entityClass == $doctrine->getAliasNamespace($bundleName).'\\'. $entityName)
                {
                    foreach( $entity['entity'] as $fieldName => $field )
                    {
                        // Получаем имя файла и сохраняем в subscriber
                        $filename = $accessor->getValue( $curEntity, $fieldName );

                        if($filename)
                        {
                            $this->oldFiles[$entityClass][$fieldName][spl_object_hash($curEntity)] = $filename;
                        }
                    }
                }
            }
        }
    }

    /**
     * Генерируем новые значения для полей сущностей
     *
     * @param LifecycleEventArgs $args
     */
    private function preUpload(LifecycleEventArgs $args)
    {
        $doctrine = $this->container->get('doctrine');
        $accessor = PropertyAccess::createPropertyAccessor();
        $curEntity = $args->getEntity();

        // Обходим объявленные в конфигурации сущности
        foreach( $this->config['entities'] as $bundleName => $bundle )
        {
            foreach( $bundle['bundle'] as $entityName => $entity )
            {
                $entityClass = get_class($curEntity);
                // Проверяем принадлежит ли сущность тому же бандлу и классу, что и описанная в конфигурации
                if( $entityClass == $doctrine->getAliasNamespace($bundleName).'\\'. $entityName)
                {
                    foreach( $entity['entity'] as $fieldName => $field )
                    {
                        // Получаем загруженный файл и сохраняем в subscriber
                        $file = $accessor->getValue( $curEntity, $fieldName );
                        if( $file instanceof UploadedFile )
                        {
                            $this->files[$entityClass][] = [$fieldName => $file, 'entity' => $curEntity];

                            // Генерируем уникальное имя для загруженного файла
                            $filename = sha1(uniqid(mt_rand(), true)) . '.' . $file->guessExtension();
                            $accessor->setValue( $curEntity, $fieldName, $filename );
                        }
                        elseif(!empty($this->oldFiles[$entityClass][$fieldName][spl_object_hash($curEntity)]))
                        {
                            // Если старый файл должен быть удален
                            if($file === false) {
                                $accessor->setValue($curEntity, $fieldName, null);
                                $pathResolver = $this->container->get('itm.file.preview.path.resolver');
                                $uploadPath = $pathResolver->getUploadPath($curEntity);

                                $fs = new Filesystem();
                                $fs->mkdir($uploadPath);

                                $oldFilename = $this->oldFiles[$entityClass][$fieldName][spl_object_hash($curEntity)];
                                $oldFilePath = $pathResolver->getUploadPath($curEntity) . DIRECTORY_SEPARATOR . $oldFilename;
                                if ($fs->exists($oldFilePath)){
                                    $fs->remove($oldFilePath);
                                }
                            }
                            elseif (empty($file)) {
                                // Сохраняем старое имя файла, если новый файл или имя файла не были загружены
                                $accessor->setValue($curEntity, $fieldName, $this->oldFiles[$entityClass][$fieldName][spl_object_hash($curEntity)]);
                            }
                        }
                    }
                }
            }
        }
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $this->upload($args);
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $this->upload($args);
    }

    /**
     * Перемещение загруженного файла в хранилище
     *
     * @param LifecycleEventArgs $args
     */
    private function upload(LifecycleEventArgs $args)
    {
        $curEntity = $args->getEntity();
        $enKey = get_class($curEntity);
        
        // Пропускаем сущности, для которых не были загружены файлы
        if( !in_array( get_class($curEntity), array_keys($this->files) ) ) return;

        $pathResolver = $this->container->get('itm.file.preview.path.resolver');
        $uploadPath = $pathResolver->getUploadPath($curEntity);

        $fs = new Filesystem();
        $fs->mkdir($uploadPath);
        
        foreach($this->files[$enKey] as $key => $files)
        {
            $entity = $files['entity'];
            if($entity !== $curEntity) continue;
            unset($files['entity']);

            foreach( $files as $field => $file )
            {
                if ($file instanceof UploadedFile) {
                    // Копируем загруженный файл в хранилище
                    $fs->copy($file->getPathname(), $pathResolver->getPath($curEntity, $field));

                    // Удаляем старый файл
                    if(!empty($this->oldFiles[get_class($curEntity)][$field][spl_object_hash($curEntity)]) && !$this->config['save_old_file']){
                        $oldFilename = $this->oldFiles[get_class($curEntity)][$field][spl_object_hash($curEntity)];
                        $oldFilePath = $pathResolver->getUploadPath($curEntity) . DIRECTORY_SEPARATOR . $oldFilename;
                        if ($fs->exists($oldFilePath)){
                            $fs->remove($oldFilePath);
                        }

                        unset($this->oldFiles[get_class($curEntity)][$field][spl_object_hash($curEntity)]);
                    }
                }
            }
            unset($this->files[$enKey][$key]);
        }
    }
} 
