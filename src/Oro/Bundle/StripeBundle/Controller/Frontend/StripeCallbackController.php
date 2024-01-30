<?php

namespace Oro\Bundle\StripeBundle\Controller\Frontend;

use Oro\Bundle\StripeBundle\EventHandler\Exception\NotSupportedEventException;
use Oro\Bundle\StripeBundle\EventHandler\Exception\StripeEventHandleException;
use Oro\Bundle\StripeBundle\EventHandler\StripeWebhookEventHandler;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Stripe Webhook callback. Receive and handle data from Stripe service.
 */
class StripeCallbackController extends AbstractController
{
    /**
     * @Route("/handle-events", name="oro_stripe_frontend_handle_events")
     */
    public function handleEventsAction(Request $request): Response
    {
        if (empty($request->getContent())) {
            return new Response('Request content is empty', 400);
        }
        $handler = $this->container->get(StripeWebhookEventHandler::class);
        $logger = $this->container->get(LoggerInterface::class);

        try {
            $handler->handleEvent($request);
        } catch (NotSupportedEventException $e) {
            // It does not break any application logic if there are no appropriate handlers registered
            // to handle event. This may happen listening of specified events is not configured in Stripe Dashboard.
            // Return 200 response to prevent resending event again by Stripe Service.
            $logger->warning($e->getMessage(), [
                'message' => $e->getMessage(),
                'exception' => $e
            ]);
            return new Response($e->getMessage(), 200);
        } catch (StripeEventHandleException $e) {
            $logger->error($e->getMessage(), [
                'message' => $e->getMessage(),
                'exception' => $e
            ]);
            // Send bad request response with error message.  This could be helpful in logs in Stripe Dashboard.
            return new Response($e->getMessage(), 400);
        } catch (\Throwable $exception) {
            $logger->critical('Unable to handle Stripe event. ' . $exception->getMessage(), [
                'message' => $exception->getMessage(),
                'exception' => $exception
            ]);

            return new Response('Error occurs during Stripe event processing', 500);
        }

        // There are no any reason to send some information in response.
        return new Response();
    }

    public static function getSubscribedServices(): array
    {
        return array_merge(
            parent::getSubscribedServices(),
            [
                StripeWebhookEventHandler::class,
                LoggerInterface::class
            ]
        );
    }
}
