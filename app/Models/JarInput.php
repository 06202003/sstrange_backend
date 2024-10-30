<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JarInput extends Model
{
    use HasFactory, Uuid;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'guid';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    protected $fillable = [
        "zip_file_path",
        "dir_file_path",
        "filename",
        "submission_type",
        "submission_language",
        "explanation_language",
        "sim_threshold",
        "dissim_threshold",
        "maximum_reported_submission_pairs",
        "minimum_matching_length",
        "template_directory_path",
        "common_content",
        "similarity_measurement",
        "ai_generated_sample",
        "resource_path",
        "number_of_clusters",
        "number_of_stages",
        "generated_code",
        "user_id",
        "status",
        "expired",
        "result",
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * USER OBJECT
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
