<?php

namespace Lucas\FileManager\Helpers;

use Lucas\FileManager\Service\FileTypeService;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Contracts\Translation\TranslatorInterface;

class File
{
    private ?array $preview;

    /**
     * File constructor.
     */
    public function __construct(private SplFileInfo $file, private TranslatorInterface $translator, private FileTypeService $fileTypeService, private FileManager $fileManager)
    {
        $this->preview = $this->fileTypeService->preview($this->fileManager, $this->file);
    }

    /**
     * @return array|false|string
     */
    public function getDimension(): bool|array|string
    {
        return preg_match('/(gif|png|jpe?g|svg)$/i', $this->file->getExtension()) ?
            @getimagesize($this->file->getPathname()) : '';
    }

    public function getHTMLDimension(): ?string
    {
        $dimension = $this->getDimension();
        if ($dimension) {
            return "{$dimension[0]}px × {$dimension[1]}px";
        }

        return null;
    }

    public function getHTMLSize(): ?string
    {
        if ('file' === $this->getFile()->getType()) {
            $size = $this->file->getSize() / 1000;
            $kb = $this->translator->trans('size.kb');
            $mb = $this->translator->trans('size.mb');

            return $size > 1000 ? number_format(($size / 1000), 1, '.', '') . ' ' . $mb : number_format($size, 1, '.', '') . ' ' . $kb;
        }
        return null;
    }

    public function getAttribut(): ?string
    {
        if ($this->fileManager->getModule()) {
            $attr = '';
            $dimension = $this->getDimension();
            if ($dimension) {
                $width = $dimension[0];
                $height = $dimension[1];
                $attr .= "data-width=\"{$width}\" data-height=\"{$height}\" ";
            }

            if ('file' === $this->file->getType()) {
                $attr .= "data-path=\"{$this->getPreview()['path']}\"";
                $attr .= ' class="select"';
            }

            return $attr;
        }

        return null;
    }

    public function isImage(): bool
    {
        return \array_key_exists('image', $this->preview);
    }

    public function isPdf(): bool
    {
        return preg_match('/(pdf)$/i', $this->file->getExtension());
    }

    public function isVideo() : bool{
        return preg_match('/(mp4|mov|avi|mkv)$/i', $this->file->getExtension());
    }

    public function getFile(): SplFileInfo
    {
        return $this->file;
    }

    public function setFile(SplFileInfo $file): void
    {
        $this->file = $file;
    }

    public function getPreview(): array
    {
        return $this->preview;
    }

    public function setPreview(array $preview): void
    {
        $this->preview = $preview;
    }

    public function isPreviewPossible(): bool|int
    {
        return $this->isImage() || $this->isPdf() || $this->isVideo();
    }
}
