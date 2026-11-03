<?php

declare(strict_types=1);

namespace Tests\Support;

use Yii;
use yii\base\Component;

/**
 * Replaces Yii::$app->smscomponent with a recorder that stores the RAW arguments of every send() call,
 * so tests can assert argument order as well as content. Call $this->recordSms() at the start of a test.
 */
trait RecordsSms
{
    private object $sms;

    private function recordSms(): void
    {
        $this->sms = new class extends Component {
            public array $calls = [];

            public function send(...$args)
            {
                $this->calls[] = $args;
                return true;
            }
        };
        Yii::$app->set('smscomponent', $this->sms);
    }
}