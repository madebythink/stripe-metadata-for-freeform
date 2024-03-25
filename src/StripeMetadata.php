<?php
/**
 * Stripe Metadata for Freeform plugin for Craft CMS 3.x
 *
 * Provides simplified support for Stripe metadata fields for the Freeform CraftCMS plugin
 *
 * @link      think.au
 * @copyright Copyright (c) 2018 Fred Rainbird
 */

namespace madebythink\stripemetadataforfreeform;

use Craft;
use craft\base\Plugin;

use Solspace\Freeform\Freeform;
use Solspace\Freeform\Services\SubmissionsService;

use yii\base\Event;

/**
 * Class StripeMetadata
 *
 * @author    Fred Rainbird
 * @package   StripeMetadataForFreeform
 * @since     0.1.0
 *
 */
class StripeMetadata extends Plugin
{
    /**
     * @var StripeMetadata
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '0.1.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        /*
        if ($this->getSettings()->expires && $this->getSettings()->expires < strtotime("now")) {
            EmpartExtensions::log("Refreshing Token");
            Craft::$app->plugins->savePluginSettings($this, $this->getSettings()->updateAccessToken());
        }*/

        $this->initEventHandlers();

        Craft::info('Loaded Plugin', 'StripeMetadata');
        Craft::info(
            Craft::t(
                'stripe-metadata-for-freeform',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

    protected function initEventHandlers() {
        Event::on(
            SubmissionsService::class,
            SubmissionsService::EVENT_AFTER_SUBMIT,
            function (Event $event) {
                $submission = $event->getSubmission();
                $form = $submission->getForm();

                if ($submission->id && Freeform::getInstance()->payments->getBySubmissionId($submission->id)) {
                    $this->attachPaymentMetadata($event);
                } elseif ($submission->id && Freeform::getInstance()->subscriptions->getBySubmissionId($submission->id)) {
                    $this->attachSubscriptionMetadata($event);
                }
            }
        );
    }

    protected function attachSubscriptionMetadata($event) {
        $submission = $event->getSubmission();
        $metadata = [];

        foreach ($submission as $field) {
            $handle = $field->getHandle();
            if (strpos($handle, 'metadata-') !== false) {
                $ref = substr($handle, strpos($handle, "-") + 1);
                $metadata[$ref] = $field->getValue();
            }
        }
        Craft::info("Updating subscription metadata");
        Craft::info(print_r($metadata, true));

        $subscription = Freeform::getInstance()->subscriptions->getBySubmissionId($submission->id);
        $integration = $subscription->getIntegration();
        $paymentDetails = $integration ? $integration->getPaymentDetails($submission->id) : null;

        if ($paymentDetails->status != 'paid' && $paymentDetails->status != 'active')
            return;

        $access_token = $integration->fetchAccessToken();

        \Stripe\Stripe::setApiKey($access_token);
        \Stripe\Subscription::update(
          $subscription->resourceId,
          [
              'metadata' => $metadata,
          ]
        );
    }

    protected function attachPaymentMetadata($event) {
        $submission = $event->getSubmission();
        $metadata = [];

        foreach ($submission as $field) {
            $handle = $field->getHandle();
            if (strpos($handle, 'metadata-') !== false) {
                $ref = substr($handle, strpos($handle, "-") + 1);
                $metadata[$ref] = $field->getValue();
            }
        }
        Craft::info("Updating payment metadata");
        Craft::info(print_r($metadata, true));

        $payment = Freeform::getInstance()->payments->getBySubmissionId($submission->id);
        $integration = $payment->getIntegration();
        $paymentDetails = $integration ? $integration->getPaymentDetails($submission->id) : null;

        if ($paymentDetails->status != 'paid' && $paymentDetails->status != 'active')
            return;

        $access_token = $integration->fetchAccessToken();

        \Stripe\Stripe::setApiKey($access_token);
        //$pi = \Stripe\PaymentIntent::retrieve($payment->resourceId);

        \Stripe\PaymentIntent::update(
          $payment->resourceId,
          [
              'metadata' => $metadata,
          ]
        );
    }

}