<?php



$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$username = getenv('DB_USERNAME') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: 'admin123';
$source = getenv('DB_DATABASE') ?: 'tinh_shop';
$target = getenv('DB_TEST_DATABASE') ?: 'tinh_shop_test';

/**
 * Locate a PostgreSQL binary (pg_dump / psql).
 */
function findBinary(string $name): ?string
{
    $paths = [];

    // The binaries are usually installed next to the server.
    $installations = glob('C:/Program Files/PostgreSQL/*/bin', GLOB_ONLYDIR);
    $installations = array_merge($installations ?: [], glob('C:/Program Files (x86)/PostgreSQL/*/bin', GLOB_ONLYDIR) ?: []);

    foreach ($installations as $directory) {
        $paths[] = $directory.DIRECTORY_SEPARATOR.$name.'.exe';
        $paths[] = $directory.DIRECTORY_SEPARATOR.$name;
    }

    // Fall back to the PATH.
    $paths[] = $name;
    $paths[] = $name.'.exe';

    foreach ($paths as $path) {
        if (@is_file($path)) {
            return $path;
        }
    }

    return null;
}

function run(string $command, array $environment = []): int
{
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        array_merge(getenv(), $environment)
    );

    if (! is_resource($process)) {
        fwrite(STDERR, "Unable to run: {$command}\n");

        return 1;
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    $status = proc_close($process);

    if ($status !== 0 && $stderr !== '') {
        fwrite(STDERR, $stderr."\n");
    }

    return $status;
}

$pgDump = findBinary('pg_dump');
$psql = findBinary('psql');

if (! $pgDump || ! $psql) {
    fwrite(
        STDERR,
        "pg_dump / psql were not found. Install the PostgreSQL client tools "
        ."or add them to the PATH.\n"
    );

    exit(1);
}

$credentials = ['PGPASSWORD' => $password];

// 1. (Re)create the test database.
$pdo = new PDO(
    "pgsql:host={$host};port={$port};dbname=postgres",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec(
    'SELECT pg_terminate_backend(pid) FROM pg_stat_activity '
    ."WHERE datname = '{$target}' AND pid <> pg_backend_pid()"
);

$exists = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = :name');
$exists->execute(['name' => $target]);

if ($exists->fetch()) {
    $pdo->exec('DROP DATABASE "'.$target.'"');
}

$pdo->exec('CREATE DATABASE "'.$target.'"');

echo "Database \"{$target}\" created.\n";

// 2. Copy the schema (no data) from the development database.
$dumpFile = tempnam(sys_get_temp_dir(), 'tinh-shop-schema-');

$dump = escapeshellarg($pgDump)
    .' --schema-only --no-owner --no-privileges --no-comments'
    .' -h '.escapeshellarg($host)
    .' -p '.escapeshellarg($port)
    .' -U '.escapeshellarg($username)
    .' -d '.escapeshellarg($source)
    .' -f '.escapeshellarg($dumpFile);

if (run($dump, $credentials) !== 0) {
    fwrite(STDERR, "pg_dump failed.\n");
    @unlink($dumpFile);

    exit(1);
}

$restore = escapeshellarg($psql)
    .' -v ON_ERROR_STOP=1 -q'
    .' -h '.escapeshellarg($host)
    .' -p '.escapeshellarg($port)
    .' -U '.escapeshellarg($username)
    .' -d '.escapeshellarg($target)
    .' -f '.escapeshellarg($dumpFile);

if (run($restore, $credentials) !== 0) {
    fwrite(STDERR, "psql failed.\n");
    @unlink($dumpFile);

    exit(1);
}

@unlink($dumpFile);

echo "Schema copied from \"{$source}\".\n";

// 3. Copy the migration history so only the migrations that are not part
//    of the copied schema yet are applied to the test database.
$sourcePdo = new PDO(
    "pgsql:host={$host};port={$port};dbname={$source}",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$targetPdo = new PDO(
    "pgsql:host={$host};port={$port};dbname={$target}",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$targetPdo->exec('TRUNCATE TABLE "migrations"');

$insert = $targetPdo->prepare(
    'INSERT INTO "migrations" ("batch", "migration") VALUES (:batch, :migration)'
);

$migrations = $sourcePdo->query(
    'SELECT "batch", "migration" FROM "migrations" ORDER BY "batch"'
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($migrations as $migration) {
    $insert->execute([
        'batch' => $migration['batch'],
        'migration' => $migration['migration'],
    ]);
}

echo count($migrations)." migrations copied.\n";

// 4. Apply the migrations that are not part of the copied schema yet
//    (for example the consumed_phone_reset_authentications table).
// The cached configuration would ignore the environment variables, so it
// is cleared before the migrations run.
run(escapeshellarg(PHP_BINARY).' artisan config:clear --no-interaction');

$artisan = escapeshellarg(PHP_BINARY).' artisan migrate --force --no-interaction';

if (run($artisan, ['DB_DATABASE' => $target]) !== 0) {
    fwrite(STDERR, "The migrations could not be applied.\n");

    exit(1);
}

echo "Test database ready.\n";
