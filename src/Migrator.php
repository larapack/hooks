<?php

namespace Larapack\Hooks;

use Illuminate\Database\Migrations\Migrator as BaseMigrator;
use Illuminate\Support\Arr;

/*
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
*/

class Migrator extends BaseMigrator
{
    public function runFiles(array $files, array $options = [])
    {
        $this->notes = [];

        $this->requireFiles($migrations = $this->pendingMigrations(
            $files,
            $this->repository->getRan()
        ));

        // Once we have all these migrations that are outstanding we are ready to run
        // we will go ahead and run them "up". This will execute each migration as
        // an operation against a database. Then we'll return this list of them.
        $this->runPending($migrations, $options);

        return $migrations;
    }

    public function resetFiles(array $files = [], $pretend = false)
    {
        $this->notes = [];

        $files = collect($files)->keyBy(function ($file) {
            return rtrim(basename($file), '.php');
        })->all();

        // Next, we will reverse the migration list so we can run them back in the
        // correct order for resetting this database. This will allow us to get
        // the database back into its "empty" state ready for the migrations.
        $migrations = array_reverse($this->repository->getRan());

        if (count($migrations) === 0) {
            $this->note('<info>Nothing to rollback.</info>');

            return [];
        }

        return $this->resetMigrationsByFiles($migrations, $files, $pretend);
    }

    protected function resetMigrationsByFiles(array $migrations, array $files, $pretend = false)
    {
        // Since the getRan method that retrieves the migration name just gives us the
        // migration name, we will format the names into objects with the name as a
        // property on the objects so that we can pass it to the rollback method.
        $migrations = collect($migrations)->map(function ($m) {
            return (object) ['migration' => $m];
        })->all();

        return $this->rollbackMigrationsByFiles(
            $migrations,
            $files,
            compact('pretend')
        );
    }

    protected function rollbackMigrationsByFiles(array $migrations, $files, array $options)
    {
        $rolledBack = [];

        $this->requireFiles($files);

        // Next we will run through all of the migrations and call the "down" method
        // which will reverse each migration in order. This getLast method on the
        // repository already returns these migration's names in reverse order.
        foreach ($migrations as $migration) {
            $migration = (object) $migration;

            if (!$file = Arr::get($files, $migration->migration)) {
                $this->note("<fg=red>Migration not found:</> {$migration->migration}");

                continue;
            }

            $rolledBack[] = $file;

            $this->runDown(
                $file,
                $migration,
                Arr::get($options, 'pretend', false)
            );
        }

        return $rolledBack;
    }
}
