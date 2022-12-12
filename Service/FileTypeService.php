<?php

namespace Lucas\FileManager\Service;

use Lucas\FileManager\Helpers\FileManager;
use SplFileInfo;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class FileTypeService {
    const IMAGE_SIZE = [
        FileManager::VIEW_LIST => '22',
        FileManager::VIEW_THUMBNAIL => '100',
    ];

    /**
     * FileTypeService constructor.
     */
    public function __construct(private RouterInterface $router, private Environment $twig) {
    }

    public function preview(FileManager $fileManager, SplFileInfo $file) {
        if ($fileManager->getImagePath()) {
            $filePath = $fileManager->getImagePath() . $file->getFilename();
        } else {
            $filePath = $this->router->generate(
                'file_manager_file',
                array_merge($fileManager->getQueryParameters(), ['fileName' => rawurlencode($file->getFilename())])
            );
        }
        $extension = $file->getExtension();
        $type = $file->getType();
        if ('file' === $type) {
            $size = $this::IMAGE_SIZE[$fileManager->getView()];

            return $this->fileIcon($filePath, $extension, $size, true, $fileManager->getConfigurationParameter('twig_extension'), $fileManager->getConfigurationParameter('cachebreaker'));
        }
        if ('dir' === $type) {
            $href = $this->router->generate(
                'file_manager', array_merge(
                $fileManager->getQueryParameters(),
                    ['route' => $fileManager->getRoute().'/'.rawurlencode($file->getFilename())]
            )
            );

            return [
                'path' => $filePath,
                'html' => '<i class="icofont-ui-folder"></i>',
                'folder' => '<a  href="'.$href.'" title="Ouvrir le dossier">'.$file->getFilename().'</a>',
            ];
        }
    }

    public function accept($type): bool|string {
        switch ($type) {
            case 'image':
                $accept = 'image/*';
                break;
            case 'media':
                $accept = 'video/*';
                break;
            default:
                return false;
        }

        return $accept;
    }

    public function fileIcon(string $filePath,?string $extension = null, ?int $size = 75, ?bool $lazy = false, ?string $twigExtension = null, ?bool $cachebreaker = null): array {
        $imageTemplate = null;

        if (null === $extension) {
            $filePathTmp = strtok($filePath, '?');
            $extension = pathinfo($filePathTmp, PATHINFO_EXTENSION);
        }
        switch (true) {
            case $this->isYoutubeVideo($filePath):
            case preg_match('/(mp4|ogg|webm|avi|wmv|mov)$/i', $extension):
                $class = 'icofont-file-video';
                break;
            case preg_match('/(mp3|wav)$/i', $extension):
                $class = 'icofont-file-audio';
                break;
            case preg_match('/(gif|png|jpe?g|svg)$/i', $extension):

                $fileName = $filePath;
                if ($cachebreaker) {
                    $query = parse_url($filePath, PHP_URL_QUERY);
                    $time = 'time='.time();
                    $fileName = $query ? $filePath.'&'.$time : $filePath.'?'.$time;
                }

                if ($twigExtension) {
                    $imageTemplate = str_replace('$IMAGE$', 'file_path', $twigExtension);
                }

                $html = $this->twig->render('@FileManager/views/preview.html.twig', [
                    'filename' => $fileName,
                    'size' => $size,
                    'lazy' => $lazy,
                    'twig_extension' => $twigExtension,
                    'image_template' => $imageTemplate,
                    'file_path' => $filePath,

                ]);

                return [
                    'path' => $filePath,
                    'html' => $html,
                    'image' => true,
                ];
            case preg_match('/(pdf)$/i', $extension):
                $class = 'icofont-file-pdf';
                break;
            case preg_match('/(docx?)$/i', $extension):
                $class = 'icofont-file-word';
                break;
            case preg_match('/(xlsx?|csv)$/i', $extension):
                $class = 'icofont-file-excel';
                break;
            case preg_match('/(pptx?)$/i', $extension):
                $class = 'icofont-file-powerpoint';
                break;
            case preg_match('/(zip|rar|gz)$/i', $extension):
                $class = 'icofont-file-zip';
                break;
            case filter_var($filePath, FILTER_VALIDATE_URL):
                $class = 'icofont-web';
                break;
            default:
                $class = 'icofont-file-alt';
        }

        return [
            'path' => $filePath,
            'html' => "<i class='{$class}'></i>",
        ];
    }

    public function isYoutubeVideo($url): bool|int {
        $rx = '~
              ^(?:https?://)?                            
               (?:www[.])?                               
               (?:youtube[.]com/watch[?]v=|youtu[.]be/)  
               ([^&]{11})                                
                ~x';

        return preg_match($rx, $url, $matches);
    }

    public function isPdf($extension) : bool|int {
        return preg_match('/(pdf)$/i', $extension);
    }
}
