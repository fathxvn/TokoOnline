<?php 
 
namespace App\Models; 
 
use Illuminate\Database\Eloquent\Factories\HasFactory; 
use Illuminate\Foundation\Auth\User as Authenticatable; 
use Illuminate\Notifications\Notifiable; 
 
class Customer extends Authenticatable 
{ 
    use HasFactory, Notifiable; 
 
    protected $table = 'customer'; 
 
    protected $fillable = [ 
        'user_id', 
        'nama', 
        'email', 
        'status', 
        'role', 
        'password', 
        'hp', 
        'alamat', 
        'pos', 
        'foto', 
        'google_id', 
        'google_token', 
    ]; 
 
    public function user() 
    { 
        return $this->belongsTo(User::class, 'user_id', 'id'); 
    } 
 
    public static function boot() 
    { 
        parent::boot(); 
 
        static::deleting(function ($customer) {
            if (Order::where('customer_id', $customer->id)->orWhere('user_id', $customer->user_id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'customer' => 'Customer memiliki pesanan atau pembayaran dan tidak dapat dihapus. Gunakan status Nonaktif.',
                ]);
            }
        });
        static::deleted(function ($customer) {
            // Delete the account after the unreferenced customer row is removed.
            if ($customer->user) $customer->user->delete();
        });
    } 
 
    protected $hidden = [ 
        'password', 
        'remember_token', 
    ]; 
 
    protected $casts = [ 
        'email_verified_at' => 'datetime', 
    ]; 
}
