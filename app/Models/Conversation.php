<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'model_name',
        'tokens',
        'summary',
        'summary_flag',
        'summary_message_id',
    ];

    /**
     * Les attributs qui doivent être castés.
     *
     * @var array
     */
    protected $casts = [
        'tokens' => 'integer',
        'summary_flag' => 'boolean',
        'summary_message_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Relation avec les documents associés à cette conversation.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ConversationDocument::class);
    }

    /**
     * Récupère les IDs des documents associés à cette conversation.
     */
    public function getDocumentIds(): array
    {
        return $this->documents()->pluck('document_id')->toArray();
    }
}
