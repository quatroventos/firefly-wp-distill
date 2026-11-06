<?php

namespace FireflyDistill\Model;

final class Host
{
    public int $id;
    public string $host;
    public int $post_id;
    public int $https;
    public int $flag_robots_index;
    public int $flag_sitemap;
    public int $flag_llm_txt;
    public int $static_enabled;
    public ?string $openseo_project_id;
    public ?string $gsc_property;
    public ?string $current_build_id;
    public int $is_primary;
    public string $created_at;
    public string $updated_at;

    /** @var HostRoute[] */
    public array $routes = [];

    /**
     * @param array<string,mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $h = new self();
        $h->id = (int) $row['id'];
        $h->host = (string) $row['host'];
        $h->post_id = (int) $row['post_id'];
        $h->https = (int) $row['https'];
        $h->flag_robots_index = (int) $row['flag_robots_index'];
        $h->flag_sitemap = (int) $row['flag_sitemap'];
        $h->flag_llm_txt = (int) $row['flag_llm_txt'];
        $h->static_enabled = (int) $row['static_enabled'];
        $h->openseo_project_id = isset($row['openseo_project_id']) && $row['openseo_project_id'] !== ''
            ? (string) $row['openseo_project_id']
            : null;
        $h->gsc_property = isset($row['gsc_property']) && $row['gsc_property'] !== ''
            ? (string) $row['gsc_property']
            : null;
        $h->current_build_id = isset($row['current_build_id']) && $row['current_build_id'] !== ''
            ? (string) $row['current_build_id']
            : null;
        $h->is_primary = (int) $row['is_primary'];
        $h->created_at = (string) $row['created_at'];
        $h->updated_at = (string) $row['updated_at'];
        return $h;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'host' => $this->host,
            'post_id' => $this->post_id,
            'https' => $this->https,
            'flag_robots_index' => $this->flag_robots_index,
            'flag_sitemap' => $this->flag_sitemap,
            'flag_llm_txt' => $this->flag_llm_txt,
            'static_enabled' => $this->static_enabled,
            'openseo_project_id' => $this->openseo_project_id,
            'gsc_property' => $this->gsc_property,
            'current_build_id' => $this->current_build_id,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'routes' => array_map(static fn (HostRoute $r) => $r->toArray(), $this->routes),
            'internal_permalink' => $this->post_id > 0 && function_exists('get_permalink')
                ? (string) get_permalink($this->post_id)
                : '',
            'public_url' => ($this->https ? 'https://' : 'http://') . $this->host . '/',
        ];
    }
}
