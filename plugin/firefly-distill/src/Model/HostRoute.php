<?php

namespace FireflyDistill\Model;

final class HostRoute
{
    public int $id;
    public int $host_id;
    public string $path;
    public int $post_id;
    public int $in_sitemap;

    /**
     * @param array<string,mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $r = new self();
        $r->id = (int) $row['id'];
        $r->host_id = (int) $row['host_id'];
        $r->path = (string) $row['path'];
        $r->post_id = (int) $row['post_id'];
        $r->in_sitemap = (int) $row['in_sitemap'];
        return $r;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'host_id' => $this->host_id,
            'path' => $this->path,
            'post_id' => $this->post_id,
            'in_sitemap' => $this->in_sitemap,
            'internal_permalink' => $this->post_id > 0 && function_exists('get_permalink')
                ? (string) get_permalink($this->post_id)
                : '',
        ];
    }
}
