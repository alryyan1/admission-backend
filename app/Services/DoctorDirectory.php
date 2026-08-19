<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resolves doctor records live from Jawda Medical, keyed by jawda_doctor_id.
 * Memoized per-instance so one controller action makes at most one HTTP call
 * regardless of how many models it needs to attach doctor data to.
 */
class DoctorDirectory
{
    /** @var array<int, array{id: int, name: string, specialist: ?string}>|null */
    private ?array $byId = null;

    public function __construct(private readonly JawdaMedicalClient $client) {}

    /**
     * @return array<int, array{id: int, name: string, specialist: ?string}>
     */
    private function all(): array
    {
        return $this->byId ??= collect($this->client->allDoctors())
            ->mapWithKeys(fn (array $doctor) => [
                $doctor['id'] => [
                    'id' => $doctor['id'],
                    'name' => $doctor['name'],
                    'specialist' => $doctor['specialist_name'] ?? null,
                ],
            ])
            ->all();
    }

    /**
     * @return array{id: int, name: string, specialist: ?string}|null
     */
    public function find(?int $id): ?array
    {
        return $id === null ? null : ($this->all()[$id] ?? null);
    }

    /**
     * @return Collection<int, array{id: int, name: string, specialist: ?string}>
     */
    public function search(string $term): Collection
    {
        $doctors = collect($this->all())->values();

        if ($term !== '') {
            $needle = mb_strtolower($term);
            $doctors = $doctors->filter(fn (array $doctor) => str_contains(mb_strtolower($doctor['name']), $needle));
        }

        return $doctors->sortBy('name')->values();
    }

    /**
     * Attach the resolved doctor onto $targetAttribute of each model, looked
     * up by $idAttribute. Accepts a single model, a Collection, or a
     * paginator's underlying collection.
     */
    public function attach(Model|Collection|LengthAwarePaginator $target, string $idAttribute, string $targetAttribute): void
    {
        $models = match (true) {
            $target instanceof LengthAwarePaginator => $target->getCollection(),
            $target instanceof Collection => $target,
            default => collect([$target]),
        };

        foreach ($models as $model) {
            $model->setAttribute($targetAttribute, $this->find($model->getAttribute($idAttribute)));
        }
    }
}
