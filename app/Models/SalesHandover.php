<?php
// app/Models/SalesHandover.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SalesHandover extends Model {
  protected $fillable = [
    'code','warehouse_id','sales_id','handover_date','status','issued_by',
    'reconciled_by','otp_hash','otp_expires_at'
  ];
  public function items(){ return $this->hasMany(SalesHandoverItem::class,'handover_id'); }
}

// app/Models/SalesHandoverItem.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SalesHandoverItem extends Model {
  protected $fillable = [
    'handover_id','product_id','qty_dispatched','qty_returned_good','qty_returned_damaged','qty_sold'
  ];
  public function handover(){ return $this->belongsTo(SalesHandover::class,'handover_id'); }
}
