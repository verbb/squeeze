<?php
namespace verbb\squeeze {
    class Squeeze
    {
        public static object $plugin;
    }
}

namespace {
    use craft\helpers\Json;
    use verbb\squeeze\models\Settings;
    use verbb\squeeze\services\Service;
    use yii\base\Security;

    $vendorPath = getenv('VERBB_SQUEEZE_TEST_VENDOR');

    if (!$vendorPath || !is_file($vendorPath . '/autoload.php')) {
        fwrite(STDERR, "Set VERBB_SQUEEZE_TEST_VENDOR to a Craft 5 vendor directory.\n");
        exit(2);
    }

    require $vendorPath . '/autoload.php';
    require $vendorPath . '/yiisoft/yii2/Yii.php';
    require $vendorPath . '/craftcms/cms/src/Craft.php';
    require dirname(__DIR__, 2) . '/src/models/Settings.php';
    require dirname(__DIR__, 2) . '/src/services/Service.php';

    function check(string $label, bool $condition): void
    {
        if (!$condition) {
            throw new RuntimeException('Failed: ' . $label);
        }

        echo $label . ": PASS\n";
    }

    $securityKey = 'synthetic-security-key-for-squeeze-tests';
    $security = new Security();
    $settings = new Settings();

    \verbb\squeeze\Squeeze::$plugin = new class($settings) {
        public function __construct(private Settings $settings)
        {
        }

        public function getSettings(): Settings
        {
            return $this->settings;
        }
    };

    Craft::$app = new class($security, $securityKey) {
        public function __construct(private Security $security, private string $securityKey)
        {
        }

        public function getConfig(): object
        {
            return new class($this->securityKey) {
                public function __construct(private string $securityKey)
                {
                }

                public function getGeneral(): object
                {
                    return (object)['securityKey' => $this->securityKey];
                }
            };
        }

        public function getDb(): object
        {
            return new class {
                public function getSupportsMb4(): bool
                {
                    return true;
                }
            };
        }

        public function getSecurity(): Security
        {
            return $this->security;
        }

        public function getUser(): object
        {
            return new class {
                public function getIdentity(): ?object
                {
                    $userId = $GLOBALS['tokenUserId'] ?? null;

                    return $userId === null ? null : (object)['id' => $userId];
                }
            };
        }
    };

    $GLOBALS['tokenUserId'] = null;

    $service = new Service();
    $token = $service->createToken([7, 11], 'annual-reports');
    $payload = $service->validateToken($token);

    check('A Squeeze token round-trips its exact asset IDs and archive name', $payload === [
        'files' => [7, 11],
        'archivename' => 'annual-reports',
    ]);
    check('A Squeeze token is not valid under the general Craft signing key', $security->validateData($token, $securityKey) === false);

    $context = 'verbb/squeeze:download-token:v1';
    $tokenKey = $security->hkdf('sha256', $securityKey, null, $context, 32);
    $tokenClaims = Json::decode($security->validateData($token, $tokenKey));

    check('The default token lifetime remains one hour', is_int($tokenClaims['expires'])
        && $tokenClaims['expires'] >= time() + 3599
        && $tokenClaims['expires'] <= time() + 3600);

    $validClaims = [
        'version' => 1,
        'purpose' => 'download',
        'files' => [7, 11],
        'archivename' => 'annual-reports',
        'expires' => time() + 300,
        'userId' => null,
    ];

    $boundaryToken = $service->createToken(range(1, 1000), 'boundary');
    $boundaryPayload = $service->validateToken($boundaryToken);
    check('A token at the 1,000-file request boundary remains valid', $boundaryPayload !== null && count($boundaryPayload['files']) === 1000);

    try {
        $service->createToken(range(1, 1001), 'too-many');
        throw new RuntimeException('Expected oversized token creation to fail.');
    } catch (InvalidArgumentException) {
        check('Token creation rejects more than 1,000 asset IDs', true);
    }

    $oversizedClaims = $validClaims;
    $oversizedClaims['files'] = range(1, 1001);
    $oversizedToken = $security->hashData(Json::encode($oversizedClaims), $tokenKey);
    check('Token validation rejects more than 1,000 asset IDs', $service->validateToken($oversizedToken) === null);

    $generalKeyToken = $security->hashData(Json::encode($validClaims), $securityKey);

    check('A general Craft signature cannot authorize a Squeeze download', $service->validateToken($generalKeyToken) === null);

    $wrongPurpose = $validClaims;
    $wrongPurpose['purpose'] = 'another-purpose';
    $wrongPurposeToken = $security->hashData(Json::encode($wrongPurpose), $tokenKey);

    check('A token with the wrong purpose is rejected', $service->validateToken($wrongPurposeToken) === null);

    $invalidResources = $validClaims;
    $invalidResources['files'] = ['7', 11];
    $invalidResourcesToken = $security->hashData(Json::encode($invalidResources), $tokenKey);

    check('A token with non-canonical asset IDs is rejected', $service->validateToken($invalidResourcesToken) === null);

    $invalidUserBinding = $validClaims;
    $invalidUserBinding['userId'] = '42';
    $invalidUserBindingToken = $security->hashData(Json::encode($invalidUserBinding), $tokenKey);

    check('A token with a non-canonical user binding is rejected', $service->validateToken($invalidUserBindingToken) === null);

    $expired = $validClaims;
    $expired['expires'] = time() - 1;
    $expiredToken = $security->hashData(Json::encode($expired), $tokenKey);

    check('An expired token is rejected', $service->validateToken($expiredToken) === null);
    check('An altered token is rejected', $service->validateToken($token . 'x') === null);

    $GLOBALS['tokenUserId'] = 42;
    $boundToken = $service->createToken([7], 'member-files', null, true);

    check('A user-bound token works for the user it was minted for', $service->validateToken($boundToken) === [
        'files' => [7],
        'archivename' => 'member-files',
    ]);

    $GLOBALS['tokenUserId'] = 99;
    check('A user-bound token is rejected for a different user', $service->validateToken($boundToken) === null);

    $GLOBALS['tokenUserId'] = null;
    check('A user-bound token is rejected for an anonymous request', $service->validateToken($boundToken) === null);

    try {
        $service->createToken([7], 'member-files', null, true);
        throw new RuntimeException('Expected anonymous user binding to fail.');
    } catch (InvalidArgumentException) {
        check('A user-bound token cannot be minted while logged out', true);
    }

    $settings->defaultTokenDuration = null;
    $permanentToken = $service->createToken([23], 'permanent');
    $permanentClaims = Json::decode($security->validateData($permanentToken, $tokenKey));

    check('Configured permanent links retain an explicit null expiry', $permanentClaims['expires'] === null);
    check('Shareable links carry an explicit null user binding', $permanentClaims['userId'] === null);
    check('A configured permanent link remains valid', $service->validateToken($permanentToken) === [
        'files' => [23],
        'archivename' => 'permanent',
    ]);
}
