<?php
/**
 * Group model — all database access for the `groups` table.
 *
 * Every query uses prepared statements with bound parameters. The PDO
 * instance is injected so the model can be tested against any database
 * (the test suite injects an in-memory SQLite connection).
 */
class Group
{
    /** Allowed values for the `type` column. */
    public const TYPES = [
        'environmental_group',
        'catchment_collective',
        'catchment_group',
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->db->query('SELECT * FROM `groups` ORDER BY id ASC');
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `groups` WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function create(array $data): ?array
    {
        $sql = 'INSERT INTO `groups`
                    (name, type, region, contact_email, latitude, longitude, created_at)
                VALUES
                    (:name, :type, :region, :contact_email, :latitude, :longitude, :created_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name'          => $data['name'],
            ':type'          => $data['type'],
            ':region'        => $data['region'],
            ':contact_email' => $data['contact_email'],
            ':latitude'      => $data['latitude'],
            ':longitude'     => $data['longitude'],
            ':created_at'    => date('Y-m-d H:i:s'),
        ]);

        return $this->find((int) $this->db->lastInsertId());
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $data): ?array
    {
        $sql = 'UPDATE `groups` SET
                    name = :name,
                    type = :type,
                    region = :region,
                    contact_email = :contact_email,
                    latitude = :latitude,
                    longitude = :longitude
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name'          => $data['name'],
            ':type'          => $data['type'],
            ':region'        => $data['region'],
            ':contact_email' => $data['contact_email'],
            ':latitude'      => $data['latitude'],
            ':longitude'     => $data['longitude'],
            ':id'            => $id,
        ]);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM `groups` WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
