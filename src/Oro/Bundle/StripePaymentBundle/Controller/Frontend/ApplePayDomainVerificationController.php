<?php

namespace Oro\Bundle\StripePaymentBundle\Controller\Frontend;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\StripePaymentBundle\DependencyInjection\Configuration;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the Apple Pay domain verification file data from the system configuration.
 */
class ApplePayDomainVerificationController extends AbstractController
{
    public function __invoke(): Response
    {
        $website = $this->container->get(WebsiteManager::class)->getCurrentWebsite();
        $configName = Configuration::getConfigKeyByName(Configuration::APPLE_PAY_DOMAIN_VERIFICATION);
        $verificationContent = $this->container->get(ConfigManager::class)->get($configName, false, false, $website);

        if (empty($verificationContent)) {
            /** @var LoggerInterface $logger */
            $logger = $this->container->get(LoggerInterface::class);

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

    #[\Override]
    public static function getSubscribedServices(): array
    {
        return [
            ...parent::getSubscribedServices(),
            WebsiteManager::class,
            ConfigManager::class,
            LoggerInterface::class,
        ];
    }
}
