<?php

namespace Oro\Bundle\StripeBundle\Controller\Frontend;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Serves the Apple Pay domain verification file data from system config
 * for current website at the predefined route
 */
class ApplePayVerificationController extends AbstractController
{
    /**
    * @Route(
    *     "/.well-known/apple-developer-merchantid-domain-association",
    *     name="oro_stripe_frontend_apple_pay_verification")
    */
    public function verificationAction(): Response
    {
        $website = $this->get(WebsiteManager::class)->getCurrentWebsite();

        $verificationContent = $this->get(ConfigManager::class)
            ->get('oro_stripe.apple_pay_domain_verification', false, false, $website);

        if (empty($verificationContent)) {
            /** @var LoggerInterface $logger */
            $logger = $this->get(LoggerInterface::class);

            $message = 'Apple Pay domain verification file data not found in system config.';
            $logger->error($message, [
                'website' => $website,
            ]);

            return new Response($message, Response::HTTP_NOT_FOUND);
        }

        $response = new Response($verificationContent);
        $response->headers->set('Content-Type', 'text/plain');

        return $response;
    }

    public static function getSubscribedServices(): array
    {
        return array_merge(
            parent::getSubscribedServices(),
            [
                WebsiteManager::class,
                ConfigManager::class,
                LoggerInterface::class,
            ]
        );
    }
}
