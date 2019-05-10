<?php

namespace App\Http\Controllers;

use App\Bill;
use App\BillDetail;
use App\Http\Requests\OrderRequest;
use App\Table;
use Carbon\Carbon;
use DB;
use Log;
use Illuminate\Http\Request;
use Validator;

class BillController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(OrderRequest $request) {
        $idUser = $request->id_user;
        $idTable = $request->id_table;
        $table = Table::find($idTable);
        $idUser = $request->id_user;
     
        $cart = json_decode($request->cart)->cartItem;   

        if ($table->status == 1) { //===== EDIT =====
            if ($this->editOrder($idTable, $idUser, $cart) == true) {
                return ORDER_SUCCESS;
            } else {
                return ORDER_FAIL;
            }
        } else { //===== ADD =====
            if ($this->addOrder($table, $idTable, $idUser, $cart) == true) {
                return ORDER_SUCCESS;
            } else {
                return ORDER_FAIL;
            }
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        $result = Bill::find($id);
        return ($result == null) ? BILL_NOT_FOUND : $result->bill_detail;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id) {

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) { //Thanh toán
        $validator = Validator::make($request->all(), [
            'id_user' => 'required',
        ],
        [
            'id_user.required' => EMPTY_ID_USER,
        ]);

        if ($validator->fails()) {
            return $validator->errors()->first();
        } else {
            $table = Table::find($id);
            if ($table->status == 0) { //Trường hợp tính tiền mà bàn có trạng thái rỗng là sai
                return TABLE_AVALIABLE;
            } else {
                $result = $this->payment($table, $request);
                return ($result == false) ? PAYMENT_FAIL : $result;
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        //
    }

    public function madeMenu(Request $request, $id){
        $bill = Bill::find($id);
        if($bill->status == 0){
            $bill->status = 1;
            $bill->save();
            return MADE_MENU_SUCCESS;
        }
        return MADE_MENU_FAIL;
    }

    private function payment($table, $request) {
        $bill = $table->bill->last();
        $total = 0;
        foreach ($bill->bill_detail as $val) {
            $total += ($val->quantity * $val->price);
        }
        try {
            DB::transaction(function () use ($bill, $request, $table, $total) {
                $bill->update([
                    'id_user_out' => $request->id_user,
                    'time_out' => Carbon::now('Asia/Ho_Chi_Minh'),
                    'total' => $total,
                ]);
                $table->update(['status' => 0]);
            });
            return $total;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function addOrder($table, $idTable, $idUser, $cart) {
        $bill = new Bill();
        $bill->time_in = Carbon::now('Asia/Ho_Chi_Minh');
        $bill->time_out = null;
        $bill->total = 0;
        $bill->status = 0;
        $bill->id_user_in = $idUser;
        $bill->id_user_out = null;
        $bill->id_table = $idTable;

        try {
            if ($bill->save()) {
                DB::transaction(function () use ($table, $bill, $cart) {
                    $table->update(['status' => 1]); //Bật trạng thái bàn đã sử dụng
                    $this->addBillDetail($bill->id, $cart);
                });
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    private function editOrder($idTable, $idUser, $cart) {
        $lastBill = Table::find($idTable)->bill->last();
        //Xóa các BillDetail của Bill cuối cùng của bàn này đang sử dụng
        DB::table('bill_details')->where('id_bill', $lastBill->id)->delete();

        //Cập nhật lại user edit lại Order
        $lastBill->update(['id_user_in' => $idUser]);

        //Thêm lại vào BillDetail
        try {
            DB::transaction(function () use ($lastBill, $cart) {
                $this->addBillDetail($lastBill->id, $cart);
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function addBillDetail($id_bill, $cartItem) {
        foreach ($cartItem as $key => $val) {
            $billDetail = new BillDetail();
            $billDetail->id_bill = $id_bill;
            $billDetail->id_product = $val->product->id;
            $billDetail->quantity = $val->quantity;
            $billDetail->price = $val->product->price;
            $billDetail->discount = $val->discount;
            $billDetail->save();
        }
    }
}