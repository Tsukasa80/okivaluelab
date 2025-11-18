<?php

namespace FluentForm\App\Modules\Payments;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\App\Modules\Payments\PaymentMethods\BaseProcessor;
use FluentForm\Database\Migrations\Submissions;
use FluentForm\Framework\Helpers\ArrayHelper;
use FluentForm\App\Modules\Payments\Classes\PaymentManagement;
use FluentForm\App\Modules\Payments\Migrations\Migration;
use FluentForm\App\Modules\Payments\PaymentMethods\Stripe\ConnectConfig;

class AjaxEndpoints
{
    public function handleEndpoint($route)
    {
        $validRoutes = [
            'enable_payment'               => 'enablePaymentModule',
            'update_global_settings'       => 'updateGlobalSettings',
            'get_payment_method_settings'  => 'getPaymentMethodSettings',
            'save_payment_method_settings' => 'savePaymentMethodSettings',
            'get_form_settings'            => 'getFormSettings',
            'save_form_settings'           => 'saveFormSettings',
            'update_transaction'           => 'updateTransaction',
            'get_stripe_connect_config'    => 'getStripeConnectConfig',
            'disconnect_stripe_connection' => 'disconnectStripeConnect',
            'get_pages'                    => 'getWpPages',
            'cancel_subscription'          => 'cancelSubscription'
        ];

        if (isset($validRoutes[$route])) {
            $this->{$validRoutes[$route]}();
        } else {
            do_action('fluentform/handle_payment_ajax_endpoint', $route);
        }

        die();
    }

    public function enablePaymentModule()
    {
        $this->upgradeDb();
        // Update settings
        $settings = PaymentHelper::updatePaymentSettings([
            'status' => 'yes'
        ]);
        // send response to reload the page

        wp_send_json_success([
            'message'  => __('Payment Module successfully enabled!', 'fluentform'),
            'settings' => $settings,
            'reload'   => 'yes'
        ]);
    }

    private function upgradeDB()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'fluentform_transactions';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder -- Checking table structure, %1s is for identifier
        $cols = $wpdb->get_col($wpdb->prepare("DESC %1s", $table), 0);

        if ($cols && in_array('subscription_id', $cols) && in_array('transaction_hash', $cols)) {
            // We are good
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder -- Migration, dropping table to recreate, %1s is for identifier
            $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %1s", $table));
            Migration::migrate();
            // Migrate the database
            Submissions::migrate(true); // Add payment_total
        }
    }

    public function updateGlobalSettings()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in route registration, sanitized in updatePaymentSettings()
        $settings = isset($_REQUEST['settings']) ? wp_unslash($_REQUEST['settings']) : [];

        // Update settings
        $settings = PaymentHelper::updatePaymentSettings($settings);

        // send response to reload the page
        wp_send_json_success([
            'message'  => __('Settings successfully updated!', 'fluentform'),
            'settings' => $settings,
            'reload'   => 'yes'
        ]);

    }

    public function getPaymentMethodSettings()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in route registration
        $method = isset($_REQUEST['method']) ? sanitize_text_field(wp_unslash($_REQUEST['method'])) : '';

        $paymentSettings = apply_filters_deprecated(
            'fluentform_payment_settings_' . $method,
            [
                []
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/payment_settings_' . $method,
            'Use fluentform/payment_settings_' . $method . ' instead of fluentform_payment_settings_' . $method
        );

        $settings = apply_filters('fluentform/payment_settings_' . $method, $paymentSettings);

        wp_send_json_success([
            'settings' => ($settings) ? $settings : false
        ]);
    }

    public function savePaymentMethodSettings()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in route registration
        $method = isset($_REQUEST['method']) ? sanitize_text_field(wp_unslash($_REQUEST['method'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in route registration, sanitized in validation filter
        $settings = isset($_REQUEST['settings']) ? wp_unslash($_REQUEST['settings']) : [];

        $settingsValidation = apply_filters_deprecated(
            'fluentform_payment_method_settings_validation_' . $method,
            [
                [],
                $settings
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/payment_method_settings_validation_' . $method,
            'Use fluentform/payment_method_settings_validation_' . $method . ' instead of fluentform_payment_method_settings_validation_' . $method
        );

        $validationErrors = apply_filters('fluentform/payment_method_settings_validation_' . $method, $settingsValidation, $settings);

        if ($validationErrors) {
            wp_send_json_error([
                'message' => __('Failed to save settings', 'fluentform'),
                'errors'  => $validationErrors
            ], 423);
        }

        $settings = apply_filters_deprecated(
            'fluentform_payment_method_settings_save_' . $method,
            [
                $settings
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/payment_method_settings_save_' . $method,
            'Use fluentform/payment_method_settings_save_' . $method . ' instead of fluentform_payment_method_settings_save_' . $method
        );

        $settings = apply_filters('fluentform/payment_method_settings_save_' . $method, $settings);

        update_option('fluentform_payment_settings_' . $method, $settings, 'yes');

        wp_send_json_success([
            'message' => __('Settings successfully updated', 'fluentform')
        ]);
    }

    public function getFormSettings()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in route registration
        $formId = isset($_REQUEST['form_id']) ? (int) $_REQUEST['form_id'] : 0;
        $settings = PaymentHelper::getFormSettings($formId, 'admin');
        $form = wpFluent()->table('fluentform_forms')->find($formId);
        $addressFields = array_values(FormFieldsParser::getAddressFields($form));

        $paymentSettings = [
            'settings'        => $settings,
            'currencies'      => PaymentHelper::getCurrencies(),
            'payment_methods' => PaymentHelper::getFormPaymentMethods($formId),
            'addressFields'   => array_filter($addressFields)
        ];

        $paymentSettings = apply_filters('fluentform/form_payment_settings', $paymentSettings, $formId);

        wp_send_json_success($paymentSettings, 200);
    }

    public function saveFormSettings()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in route registration
        $formId = isset($_REQUEST['form_id']) ? (int) $_REQUEST['form_id'] : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in route registration, sanitized in setFormMeta()
        $settings = isset($_REQUEST['settings']) ? wp_unslash($_REQUEST['settings']) : [];
        Helper::setFormMeta($formId, '_payment_settings', $settings);

        wp_send_json_success([
            'message' => __('Settings successfully saved', 'fluentform')
        ], 200);
    }

    public function updateTransaction()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified in route registration, data sanitized below
        $transactionData = isset($_REQUEST['transaction']) ? $_REQUEST['transaction'] : [];
        $transactionId = (int) $transactionData['id'];
        $oldTransaction = wpFluent()->table('fluentform_transactions')
            ->find($transactionId);

        $changingStatus = $oldTransaction->status != $transactionData['status'];

        $updateData = ArrayHelper::only($transactionData, [
            'payer_name',
            'payer_email',
            'billing_address',
            'shipping_address',
            'charge_id',
            'status'
        ]);

        $updateData['updated_at'] = current_time('mysql');

        wpFluent()->table('fluentform_transactions')
            ->where('id', $transactionId)
            ->update($updateData);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in route registration
        $subscriptionId = (int) ArrayHelper::get($_REQUEST, 'subscription_id', '0');
        if ($subscriptionId) {
            $existingSubscription = wpFluent()->table('fluentform_subscriptions')
                                        ->find($subscriptionId);

            $changedStatus = ArrayHelper::get($transactionData, 'status');

            $isStatusChanged = $existingSubscription->status != $changedStatus;

            if ($isStatusChanged) {
                wpFluent()->table('fluentform_subscriptions')
                          ->where('id', $subscriptionId)
                          ->update([
                              'status' => $changedStatus,
                              'updated_at' => current_time('mysql')
                          ]);
            }
        }

        $newStatus = $transactionData['status'];

        // No need abstract method, only need defined method, empty implementation
        $baseProcessor = new class extends BaseProcessor {
            public function handlePaymentAction($submissionId, $submissionData, $form, $methodSettings, $hasSubscriptions, $totalPayable) {
            }
        };

        if (
            ($changingStatus && ($newStatus == 'refunded' || $newStatus == 'partial-refunded')) ||
            ($newStatus == 'partial-refunded' && ArrayHelper::get($transactionData, 'refund_amount'))
        ) {
            $refundAmount = 0;
            $refundNote = 'Refunded by Admin';

            if ($newStatus == 'refunded') {
                // Handle refund here
                $refundAmount = $oldTransaction->payment_total;
            } else if ($newStatus == 'partially-refunded') {
                $refundAmount = ArrayHelper::get($transactionData, 'refund_amount') * 100;
                $refundNote = ArrayHelper::get($transactionData, 'refund_note');
            }

            if ($refundAmount) {
                $baseProcessor->setSubmissionId($oldTransaction->submission_id);

                $submission = $baseProcessor->getSubmission();
                $baseProcessor->refund($refundAmount, $oldTransaction, $submission, $oldTransaction->payment_method, 'refund_' . time(), $refundNote);
            }

        }

        if ($changingStatus) {

            if ($newStatus == 'paid' || $newStatus == 'pending' || $newStatus == 'processing') {
                // Delete All Refunds
                wpFluent()->table('fluentform_transactions')
                    ->where('submission_id', $oldTransaction->submission_id)
                    ->where('transaction_type', 'refund')
                    ->delete();
            }

            $baseProcessor->setSubmissionId($oldTransaction->submission_id);
            $baseProcessor->changeSubmissionPaymentStatus($newStatus);
            $baseProcessor->changeTransactionStatus($transactionId, $newStatus);
            $baseProcessor->recalculatePaidTotal();
        }

        $shouldRunActions = ArrayHelper::get($transactionData, 'should_run_actions', 'no');

        if (
            $changingStatus &&
            $newStatus === 'paid' &&
            $shouldRunActions === 'yes'
        ) {
            do_action(
                'fluentform/run_actions_after_update_transaction_as_paid',
                $newStatus,
                $oldTransaction
            );
        }

        wp_send_json_success([
            'message' => __('Successfully updated data', 'fluentform')
        ], 200);
    }

    public function getStripeConnectConfig()
    {
        wp_send_json_success(ConnectConfig::getConnectConfig());
    }

    public function disconnectStripeConnect()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in route registration
        return ConnectConfig::disconnect($_REQUEST, true);
    }

    public function getWpPages()
    {
        $pages = wpFluent()->table('posts')
            ->select(['ID', 'post_title'])
            ->where('post_status', 'publish')
            ->where('post_type', 'page')
            ->orderBy('ID', 'ASC')
            ->get();

        wp_send_json_success([
            'pages' => $pages
        ]);
    }

    public function cancelSubscription()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in route registration
        $subscriptionId = (int) ArrayHelper::get($_REQUEST, 'subscription_id');

        $subscription = fluentFormApi('submissions')->getSubscription($subscriptionId);

        if (!$subscription) {
            wp_send_json_error([
                'message' => __('Subscription could not be found', 'fluentform')
            ], 423);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in route registration
        $transactionId = (int) ArrayHelper::get($_REQUEST, 'transaction_id', '0');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in route registration
        $submissionId = (int) ArrayHelper::get($_REQUEST, 'submission_id', '0');

        $oldTransaction = wpFluent()->table('fluentform_transactions')
                                    ->find($transactionId);

        $oldSubmission = wpFluent()->table('fluentform_submissions')
                                   ->find($submissionId);

        if ($oldTransaction && $oldSubmission) {
            $isStatusNotCancelled = $oldTransaction->status !== 'cancelled' && $oldSubmission->payment_status !== 'cancelled';

            if ($isStatusNotCancelled) {
                $updateData =

                wpFluent()->table('fluentform_transactions')
                          ->where('id', $transactionId)
                          ->update([
                              'status' => 'cancelled',
                              'updated_at' => current_time('mysql')
                          ]);

                wpFluent()->table('fluentform_submissions')
                          ->where('id', $submissionId)
                          ->update([
                              'payment_status' => 'cancelled',
                              'updated_at' => current_time('mysql')
                          ]);
            }
        }

        $response = (new PaymentManagement())->cancelSubscription($subscription);

        if(is_wp_error($response)) {
            wp_send_json_error([
                'message' => $response->get_error_code().' - '.$response->get_error_message()
            ], 423);
        }

        wp_send_json_success([
            'message' => $response
        ]);

    }

}
