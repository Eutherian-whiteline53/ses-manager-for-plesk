<?php

namespace Library\Service;

use Library\Client\AwsSesClient;
use Library\Repository\SettingsRepository;

class FeedbackMetricsService
{
    /** @var SettingsRepository */
    private $settingsRepository;

    public function __construct(?SettingsRepository $settingsRepository = null)
    {
        $this->settingsRepository = $settingsRepository ?: new SettingsRepository();
    }

    public function getMetrics()
    {
        $credentials = $this->settingsRepository->getAwsCredentials();
        if ($credentials['accessKey'] === null || $credentials['secretKey'] === null) {
            return array(
                'success' => false,
                'message' => $this->message('credentialsMissing'),
                'rows' => $this->emptyRows(),
            );
        }

        try {
            $client = new AwsSesClient($credentials['accessKey'], $credentials['secretKey'], $credentials['region']);
            $end = strtotime('today 00:00 UTC');
            $start = $end - 86400;
            $previousStart = $start - 86400;
            $current = $this->fetchPeriod($client, $start, $end);
            $previous = $this->fetchPeriod($client, $previousStart, $start);

            return array(
                'success' => true,
                'message' => '',
                'rows' => $this->buildRows($current, $previous),
            );
        } catch (\Exception $e) {
            $this->logError('SES Manager: failed to fetch SES feedback metrics: ' . $e->getMessage());

            return array(
                'success' => false,
                'message' => $this->message('feedbackMetricsFetchFailed'),
                'rows' => $this->emptyRows(),
            );
        }
    }

    private function fetchPeriod(AwsSesClient $client, $startDate, $endDate)
    {
        $metrics = array(
            'SEND',
            'DELIVERY',
            'COMPLAINT',
            'TRANSIENT_BOUNCE',
            'PERMANENT_BOUNCE',
            'OPEN',
            'CLICK',
            'DELIVERY_OPEN',
            'DELIVERY_CLICK',
        );

        $queries = array();
        foreach ($metrics as $metric) {
            $queries[] = array(
                'Id' => strtolower($metric),
                'Namespace' => 'VDM',
                'Metric' => $metric,
                'StartDate' => $startDate,
                'EndDate' => $endDate,
            );
        }

        $response = $client->batchGetMetricData($queries);
        $values = array_fill_keys($metrics, 0.0);

        if (!empty($response['Results']) && is_array($response['Results'])) {
            foreach ($response['Results'] as $result) {
                if (empty($result['Id'])) {
                    continue;
                }

                $metric = strtoupper($result['Id']);
                if ($metric === 'TRANSIENT_BOUNCE' || $metric === 'PERMANENT_BOUNCE' || $metric === 'DELIVERY_OPEN' || $metric === 'DELIVERY_CLICK') {
                    // already normalized
                }

                if (!array_key_exists($metric, $values)) {
                    continue;
                }

                $values[$metric] = array_sum(isset($result['Values']) && is_array($result['Values']) ? $result['Values'] : array());
            }
        }

        return $values;
    }

    private function buildRows(array $current, array $previous)
    {
        $send = $current['SEND'];
        $previousSend = $previous['SEND'];

        return array(
            'sent' => $this->row('Sent', $current['SEND'], null, $this->countChange($current['SEND'], $previous['SEND'])),
            'delivered' => $this->row('Delivered', $current['DELIVERY'], $this->rate($current['DELIVERY'], $send), $this->rateDiff($current['DELIVERY'], $send, $previous['DELIVERY'], $previousSend)),
            'complaints' => $this->row('Complaints', $current['COMPLAINT'], $this->rate($current['COMPLAINT'], $send), $this->rateDiff($current['COMPLAINT'], $send, $previous['COMPLAINT'], $previousSend)),
            'transient_bounces' => $this->row('Transient bounces', $current['TRANSIENT_BOUNCE'], $this->rate($current['TRANSIENT_BOUNCE'], $send), $this->rateDiff($current['TRANSIENT_BOUNCE'], $send, $previous['TRANSIENT_BOUNCE'], $previousSend)),
            'permanent_bounces' => $this->row('Permanent bounces', $current['PERMANENT_BOUNCE'], $this->rate($current['PERMANENT_BOUNCE'], $send), $this->rateDiff($current['PERMANENT_BOUNCE'], $send, $previous['PERMANENT_BOUNCE'], $previousSend)),
            'opens' => $this->engagementRow('Opens', $current['OPEN'], $current['DELIVERY_OPEN'], $previous['OPEN'], $previous['DELIVERY_OPEN']),
            'clicks' => $this->engagementRow('Clicks', $current['CLICK'], $current['DELIVERY_CLICK'], $previous['CLICK'], $previous['DELIVERY_CLICK']),
        );
    }

    private function engagementRow($label, $value, $denominator, $previousValue, $previousDenominator)
    {
        $active = $denominator > 0 || $value > 0;
        $row = $this->row($label, $value, $active ? $this->rate($value, $denominator) : null, $active ? $this->rateDiff($value, $denominator, $previousValue, $previousDenominator) : null);
        $row['active'] = $active;

        return $row;
    }

    private function row($label, $value, $rate, $diff)
    {
        return array(
            'label' => $label,
            'value' => (int) round($value),
            'rate' => $rate,
            'diff' => $diff,
            'active' => true,
        );
    }

    private function rate($value, $total)
    {
        return $total > 0 ? round(($value / $total) * 100, 2) : null;
    }

    private function rateDiff($value, $total, $previousValue, $previousTotal)
    {
        $currentRate = $this->rate($value, $total);
        $previousRate = $this->rate($previousValue, $previousTotal);

        if ($currentRate === null || $previousRate === null) {
            return null;
        }

        return round($currentRate - $previousRate, 2);
    }

    private function countChange($value, $previousValue)
    {
        if ($previousValue <= 0) {
            return null;
        }

        return round((($value - $previousValue) / $previousValue) * 100, 2);
    }

    private function emptyRows()
    {
        return $this->buildRows(array_fill_keys(array('SEND', 'DELIVERY', 'COMPLAINT', 'TRANSIENT_BOUNCE', 'PERMANENT_BOUNCE', 'OPEN', 'CLICK', 'DELIVERY_OPEN', 'DELIVERY_CLICK'), 0), array_fill_keys(array('SEND', 'DELIVERY', 'COMPLAINT', 'TRANSIENT_BOUNCE', 'PERMANENT_BOUNCE', 'OPEN', 'CLICK', 'DELIVERY_OPEN', 'DELIVERY_CLICK'), 0));
    }

    private function logError($message)
    {
        try {
            \pm_Log::err($message);
        } catch (\Exception $e) {
            error_log($message);
        }
    }

    private function message($key)
    {
        try {
            return \pm_Locale::lmsg($key);
        } catch (\Exception $e) {
            if ($key === 'feedbackMetricsFetchFailed') {
                return 'Could not fetch SES feedback metrics. Make sure VDM metrics are available and the IAM policy allows ses:BatchGetMetricData.';
            }

            if ($key === 'credentialsMissing') {
                return 'AWS Access Key and Secret Key are required.';
            }

            return $key;
        }
    }
}
