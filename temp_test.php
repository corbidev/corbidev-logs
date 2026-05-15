<?php
try {
    $d = new DateTimeImmutable('INVALID_DATE');
    echo 'OK: ' . $d->format('c') . PHP_EOL;
} catch (\Throwable $e) {
    echo 'THROW: ' . get_class($e) . ' - ' . $e->getMessage() . PHP_EOL;
}
