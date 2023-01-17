<?php

namespace Lucas\FileManager\Service;

use Lucas\FileManager\Helpers\FileManager;
use SplFileInfo;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class FileTypeService
{
    public const IMAGE_SIZE = [
        FileManager::VIEW_LIST => '22',
        FileManager::VIEW_THUMBNAIL => '100',
    ];

    /**
     * FileTypeService constructor.
     */
    public function __construct(private RouterInterface $router, private Environment $twig, private KernelInterface $kernel)
    {
    }

    public function preview(FileManager $fileManager, SplFileInfo $file)
    {
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
                    ['route' => $fileManager->getRoute() . '/' . rawurlencode($file->getFilename())]
                )
            );

            $path = $this->router->getContext()->getBaseUrl() . '/bundles/filemanager/img/dossier.png';

            return [
                'path' => $filePath,
                'html' => '<img width="30" height="30" src="' . $path . '">',
                'folder' => '<a  href="' . $href . '" title="Ouvrir le dossier">' . $file->getFilename() . '</a>',
            ];
        }
    }

    public function accept($type): bool|string
    {
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

    public function fileIcon(string $filePath, ?string $extension = null, ?int $size = 75, ?bool $lazy = false, ?string $twigExtension = null, ?bool $cachebreaker = null): array
    {
        $imageTemplate = null;

        if (null === $extension) {
            $filePathTmp = strtok($filePath, '?');
            $extension = pathinfo($filePathTmp, PATHINFO_EXTENSION);
        }
        $fileName = 'file.png';
        switch (true) {
            case preg_match('/(mp4)$/i', $extension):
                $fileName = 'mp4.png';
                break;
            case preg_match('/(ogg)$/i', $extension):
                $fileName = 'ogg.png';
                break;
            case preg_match('/(webm)$/i', $extension):
                $fileName = 'webm.png';
                break;
            case preg_match('/(avi)$/i', $extension):
                $fileName = 'avi.png';
                break;
            case preg_match('/(mov)$/i', $extension):
                $fileName = 'mov.png';
                break;
            case preg_match('/(mp3)$/i', $extension):
                $fileName = 'mp3.png';
                break;
            case preg_match('/(wav)$/i', $extension):
                $fileName = 'wav.png';
                break;
            case preg_match('/(exe|msi)$/i', $extension):
                $fileName = 'exe.png';
                break;
            case preg_match('/(ai)$/i', $extension):
                $fileName = 'ai.png';
                break;
            case preg_match('/(an)$/i', $extension):
                $fileName = 'an.png';
                break;
            case preg_match('/(prproj)$/i', $extension):
                $fileName = 'pr.png';
                break;
            case preg_match('/(psd)$/i', $extension):
                $fileName = 'ps.png';
                break;
            case preg_match('/(xd)$/i', $extension):
                $fileName = 'xd.png';
                break;
            case preg_match('/(lr)$/i', $extension):
                $fileName = 'lr.png';
                break;
            case preg_match('/(id)$/i', $extension):
                $fileName = 'id.png';
                break;
            case preg_match('/(ttf)$/i', $extension):
                $fileName = 'ttf.png';
                break;
            case preg_match('/(otf)$/i', $extension):
                $fileName = 'otf.png';
                break;
            case preg_match('/(eot)$/i', $extension):
                $fileName = 'eot.png';
                break;
            case preg_match('/(svg)$/i', $extension):
                $fileName = 'svg.png';
                break;
            case preg_match('/(html)$/i', $extension):
                $fileName = 'html.png';
                break;
            case preg_match('/(gif|png|jpe?g|webp|jfif)$/i', $extension):

                $fileName = $filePath;
                if ($cachebreaker) {
                    $query = parse_url($filePath, PHP_URL_QUERY);
                    $time = 'time=' . time();
                    $fileName = $query ? $filePath . '&' . $time : $filePath . '?' . $time;
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
                $fileName = 'pdf.png';
                break;
            case preg_match('/(docx?)$/i', $extension):
                $fileName = 'doc.png';
                break;
            case preg_match('/(xlsx?|csv)$/i', $extension):
                $fileName = 'xls.png';
                break;
            case preg_match('/(pptx?)$/i', $extension):
                $fileName = 'ppt.png';
                break;
            case preg_match('/(zip)$/i', $extension):
                $fileName = 'zip.png';
                break;
            case preg_match('/(rar)$/i', $extension):
                $fileName = 'rar.png';
                break;
            case preg_match('/(gz)$/i', $extension):
                $fileName = 'gz.png';
                break;
        }


        $path = $this->router->getContext()->getBaseUrl() . '/bundles/filemanager/img/' . $fileName;
        return [
            'path' => $filePath,
            'html' => '<img width="30" height="30" src="' . $path . '">',
        ];
    }

}
