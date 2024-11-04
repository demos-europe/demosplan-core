<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Controller\Platform;

use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Annotation\Route;

class WSController extends BaseController
{
    /**
     * @throws \JsonException
     */
    #[Route('/publish', name: 'publish')]
    public function publish(HubInterface $hub): Response
    {
        $update = new Update(
            'https://blp.dplan.local/books/1',
            json_encode(['status' => 'OutOfStock'], JSON_THROW_ON_ERROR),
        );

        $hub->publish($update);

        return new Response('published!');
    }
}
