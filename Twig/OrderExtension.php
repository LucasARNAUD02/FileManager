<?php

namespace Lucas\FileManager\Twig;

use Lucas\FileManager\Helpers\FileManager;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class OrderExtension extends AbstractExtension
{
    const ASC = 'asc';
    const DESC = 'desc';
    const ICON = [self::ASC => 'arrow_drop_up', self::DESC => 'arrow_drop_down'];

    /**
     * OrderExtension constructor.
     */
    public function __construct(private RouterInterface $router)
    {
    }

    public function order(Environment $environment, FileManager $fileManager, $type): string {
        $order = self::ASC === $fileManager->getQueryParameter('order');
        $active = $fileManager->getQueryParameter('orderby') === $type ? 'actived' : null;
        $orderBy = [];
        $orderBy['orderby'] = $type;
        $orderBy['order'] = $active ? ($order ? self::DESC : self::ASC) : self::ASC;
        $parameters = array_merge($fileManager->getQueryParameters(), $orderBy);

        $icon = $active ? ($order ? self::ICON[self::ASC] : self::ICON[self::DESC]) : 'unfold_more';

        $href = $this->router->generate('file_manager', $parameters);

        return $environment->render('@FileManager/extension/_order.html.twig', [
            'active' => $active,
            'href' => $href,
            'icon' => $icon,
            'type' => $type,
            'islist' => 'list' === $fileManager->getView(),
        ]);
    }

    /**
     * @return array
     */
    public function getFunctions(): array {
        return [
            'order' => new TwigFunction('order', [$this, 'order'],
                ['needs_environment' => true, 'is_safe' => ['html']]),
        ];
    }
}
