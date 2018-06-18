<?php
return [
  0 => [
    'name' => 'Cron:Job.ProcessOfflineRecurringPayments',
    'entity' => 'Job',
    'update' => 'never',
    'params' => [
      'version' => 3,
      'name' => 'Job.ProcessOfflineRecurringPayments',
      'description' => 'Call Job.ProcessOfflineRecurringPayments API',
      'run_frequency' => 'Hourly',
      'api_entity' => 'Job',
      'api_action' => 'ProcessOfflineRecurringPayments',
      'parameters' => '',
    ],
  ],
];
