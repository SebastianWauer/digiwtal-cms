<?php
declare(strict_types=1);

class DashboardController
{
    public function __construct(
        private CustomerRepository $customerRepo,
        private HealthMonitor $monitor
    ) {
    }

    public function index(): void
    {
        AdminAuth::requireAuth();

        $customers = $this->customerRepo->listAllWithHealth();

        // Zustand der Ueberwachung selbst. Ohne diese Angabe ist ein alter
        // gespeicherter Status nicht von einer aktuellen Messung zu
        // unterscheiden - genau daran ist das Monitoring im August 2026
        // wochenlang unbemerkt vorbeigelaufen.
        $monitorState = $this->monitor->monitorState();

        foreach ($customers as &$customer) {
            $isActive = CustomerRepository::hasActiveSubscription($customer);
            $status = (string)($customer['health_status'] ?? 'unknown');

            $lastCheckAt = trim((string)($customer['last_check_at'] ?? ''));
            $checkAge = null;
            if ($lastCheckAt !== '') {
                $ts = strtotime($lastCheckAt);
                $checkAge = $ts === false ? null : max(0, time() - $ts);
            }

            // "Veraltet" heisst: Diese Zahlen sagen nichts ueber jetzt aus.
            $checkStale = $checkAge === null || $checkAge > HealthMonitor::RUN_STALE_AFTER;

            $lastSuccessfulHealthAt = (string)($customer['last_successful_health_at'] ?? '');
            $staleHealth = true;
            if ($lastSuccessfulHealthAt !== '') {
                $successfulTs = strtotime($lastSuccessfulHealthAt);
                $staleHealth = $successfulTs === false || (time() - $successfulTs) > 1800;
            }

            $customer['stale_health']       = $staleHealth;
            $customer['check_stale']        = $checkStale;
            $customer['check_age_seconds']  = $checkAge;
            $customer['never_checked']      = $lastCheckAt === '';

            if (!$isActive) {
                $customer['ampel'] = 'red';
            } elseif ($checkStale) {
                // Grau statt gruen oder rot: Eine veraltete Messung ist keine
                // Aussage, weder in die eine noch in die andere Richtung.
                $customer['ampel'] = 'muted';
            } elseif ($status === 'healthy') {
                $customer['ampel'] = 'green';
            } elseif ($status === 'degraded') {
                $customer['ampel'] = 'yellow';
            } else {
                $customer['ampel'] = 'red';
            }
        }
        unset($customer);

        require __DIR__ . '/../views/dashboard/index.php';
    }
}
