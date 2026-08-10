<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * Filter by post type
     *
     * @param        $query
     * @param string $type
     *
     * @return mixed
     */
    public function scopeType($query, $type = 'post')
    {
        return $query->where('post_type', '=', $type);
    }

    /**
     * Filter by post status
     *
     * @param        $query
     * @param string $status
     *
     * @return mixed
     */
    public function scopeStatus($query, $status = 'publish')
    {
        return $query->where('post_status', '=', $status);
    }

    /**
     * Filter by post author
     *
     * @param      $query
     * @param null $author
     *
     * @return mixed
     */
    public function scopeAuthor($query, $author = null)
    {
        if ($author) {
            return $query->where('post_author', '=', $author);
        }
    }

    /**
     * Get comments from the post
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comments()
    {
        return $this->hasMany('WeDevs\ORM\WP\Comment', 'comment_post_ID');
    }

    /**
     * Get meta fields from the post
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function meta()
    {
        return $this->hasMany('WeDevs\ORM\WP\PostMeta', 'post_id');
    }

    /**
     * get post thumbnail url
     *
     * @param string $size
     *
     * @return false|string
     */
    public function getThumbnailUri($size = 'thumbnail')
    {
        $thumbnailMeta = $this->getMeta('_thumbnail_id');
        return wp_get_attachment_image_url($thumbnailMeta->meta_value, $size);
    }
}
