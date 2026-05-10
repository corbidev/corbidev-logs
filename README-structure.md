src/
└── Log/
    ├── Application/
    │   ├── ✅ Factory/
    │   │   ├── LogEntryFactory.php
    │   │   └── LogEntryFactoryInterface.php
    │   │
    │   ├── ✅ Fingerprint/
    │   │   └── FingerprintGenerator.php
    │   │
    │   └── ✅ Normalizer/
    │       ├── LogPayloadNormalizer.php
    │       └── LogNormalizerConfig.php
    │
    ├── Domain/
    │   ├── ✅ Entity/
    │   │   └── LogEntry.php
    │   │
    │   ├── ✅ Exception/
    │   │   ├── InvalidFingerprintException.php
    │   │   ├── InvalidHttpStatusException.php
    │   │   ├── InvalidIpAddressException.php
    │   │   ├── InvalidLogEntryException.php
    │   │   └── InvalidUriException.php
    │   │
    │   └── ✅ ValueObject/
    │       ├── Client.php
    │       ├── Fingerprint.php
    │       ├── HttpStatus.php
    │       ├── IpAddress.php
    │       ├── Request.php
    │       ├── Tags.php
    │       └── Uri.php
    │
    ├── ✅ Enum/
    │   ├── Environment.php
    │   └── LogLevel.php
    │
    └── Infrastructure/

Ordre exact :
✅ définir le JSON d’ingestion
✅ écrire les tests du normalizer
✅ coder normalizer
✅ coder LogEntry
✅ coder LogPayloadNormalizer
✅ coder FileQueueWriter
⬛ coder le controller API
⬛ tests d’intégration ingestion
Et seulement après :
⬛ cron consumer
⬛ persistence DB
⬛ dashboard






