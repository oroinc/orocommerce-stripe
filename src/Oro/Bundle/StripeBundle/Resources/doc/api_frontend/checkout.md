# Oro\Bundle\CheckoutBundle\Entity\Checkout

## SUBRESOURCES

### paymentInfoStripe

#### add_subresource

Apply stripe-related payment information to the checkout.

Follow the [Storefront Checkout API Guide](https://doc.oroinc.com/api/checkout-api/#stripe-payment) for more details about the checkout process using the API.

{@request:json_api}
Example:

```JSON
{
  "meta": {
    "stripePaymentMethodId": "pm_stripepaymentmethodid"
  }
}
```
{@/request}

### paymentStripe

#### add_subresource

Execute checkout payment with Stripe payment method.

The Stripe payment method requires the Stripe payment method identifier to be set with the ``paymentInfoStripe`` sub-resource before the payment starts.

When the payment is complete, an application should make a second request to the subresource to complete the checkout or to properly handle payment errors.
If the payment requires additional actions, such as 3D Secure authentication, the request will fail with an error containing additional data required to complete the payment. If the payment is successful, the order resource is returned in response.

Follow the [Storefront Checkout API Guide](https://doc.oroinc.com/api/checkout-api/#stripe-payment) for more details about the checkout process using the API.

{@request:json_api}
Example of the request:

```JSON
{
  "meta": {
      "successUrl": "https://my-application.ltd/checkout/payment/stripe/success",
      "failureUrl": "https://my-application.ltd/checkout/payment/stripe/failure",
      "partiallyPaidUrl": "https://my-application.ltd/checkout/payment/stripe/partiallyPaid"
  }
}
```

Example of a response when the checkout is not ready for payment:
```JSON
{
    "errors": [
        {
            "status": "400",
            "title": "payment constraint",
            "detail": "The checkout is not ready for payment.",
            "meta": {
                "validatePaymentUrl": "https://oro-application.ltd/api/checkouts/1/payment"
            }
        }
    ]
}
```

Example of a response when for payment is still in progress:
```JSON
{
    "errors": [
        {
            "status": "400",
            "title": "payment status constraint",
            "detail": "Payment is being processed. Please follow the payment provider's instructions to complete it."
        }
    ]
}
```

Example of a response when for payment failed with error:
```JSON
{
    "errors": [
        {
            "status": "400",
            "title": "payment constraint",
            "detail": "Payment failed, please try again or select a different payment method."
        }
    ]
}
```

Example of a response when additional actions required:
```JSON
{
    "errors": [
        {
            "status": "400",
            "title": "payment action constraint",
            "detail": "The payment requires additional actions.",
            "meta": {
                "data": {
                    "paymentMethod": "stripe_payment_1",
                    "paymentMethodSupportsValidation": false,
                    "errorUrl": "http://oro-application.ltd/payment/callback/error/e111111c-1111-1111-1abc-11dc1d1111f1",
                    "returnUrl": "http://oro-application.ltd/payment/callback/return/e111111c-1111-1111-1abc-11dc1d1111f1",
                    "failureUrl": "https://my-application.ltd/checkout/payment/stripe/failure",
                    "partiallyPaidUrl": "https://my-application.ltd/checkout/payment/stripe/partiallyPaid",
                    "successUrl": "https://my-application.ltd/checkout/payment/stripe/success",
                    "additionalData": {
                        "stripePaymentMethodId": "pm_stripepaymentmethodid",
                        "customerId": "cus_stripecustomerid",
                        "paymentIntentId": "pi_stripepaymentintentid"
                    },
                    "successful": false,
                    "requires_action": true,
                    "payment_intent_client_secret": "pi_stripe_secret_key"
                }
            }
        }
    ]
}
```
{@/request}


# Oro\Bundle\StripeBundle\Api\Model\StripePaymentRequest

## FIELDS

### successUrl

The URL to which Stripe should direct customers after a successful payment completion.

### failureUrl

The URL to which Stripe should direct customers when a payment fails.

### partiallyPaidUrl

The URL to which Stripe should direct customers when payment is partially paid.


# Oro\Bundle\StripeBundle\Api\Model\StripePaymentInfoRequest

## FIELDS

### stripePaymentMethodId

The Stripe payment method identifier.
