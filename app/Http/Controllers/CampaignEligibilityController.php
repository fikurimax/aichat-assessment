<?php

namespace App\Http\Controllers;

use App\Exceptions\BadRequestException;
use App\Exceptions\ServerErrorException;
use App\Models\PurchaseTransaction;
use App\Models\Voucher;
use App\Service\PhotoVerificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CampaignEligibilityController extends Controller
{
    private PhotoVerificationService $photoVerificationService;

    public function __construct(PhotoVerificationService $photoVerificationService)
    {
        $this->photoVerificationService = $photoVerificationService;
    }

    public function eligibilityCheckerHandler(Request $request): JsonResponse
    {
        // get the voucher data to determine whether the user already get the voucher or not
        $alreadyRegistered = Voucher::firstWhere('given_to', $request->user()->customer_id);
        if ($alreadyRegistered != null) {
            throw new BadRequestException('You already participate in this campaign');
        }

        $vouchersOccupied = Voucher::whereNull('lock_for')->whereNull('given_to')->get();
        if ($vouchersOccupied->count() == 0) {
            throw new BadRequestException('Oops! all voucher already occupied');
        }

        // get the last purchasements in 30 days
        $getLastPurchasements = PurchaseTransaction::select('total_spent')
            ->where('customer_id', $request->user()->customer_id)
            ->whereBetween('created_at', [
                Carbon::now()->subDays(30)->format('Y-m-d 00:00:01'),
                Carbon::now()
            ])
            ->get();

        // the customer is not eligible if the purchasements in the last 30 days less than 3
        if ($getLastPurchasements->count() < 3) {
            throw new BadRequestException('You are not eligible in this campaign yet');
        }

        // the customer is not eligible if the total spent in the last 30 days is less than $100
        $total = $getLastPurchasements->sum(fn (PurchaseTransaction $purchaseTransaction) => $purchaseTransaction->total_spent);
        if ($total < 100.00) {
            throw new BadRequestException('You are not eligible in this campaign yet');
        }

        try {
            $user = $request->user()->customer_id;

            // run transaction to lock a voucher to the current user
            // try 5 times in case dead lock error is occured
            DB::transaction(function () use ($user) {
                $voucher = Voucher::lockForUpdate()
                    ->whereNull('lock_for')
                    ->whereNull('given_to')
                    ->inRandomOrder()
                    ->firstOrFail();

                $voucher->lock_for = $user;
                $voucher->save();
            }, 5);
        } catch (\Throwable $th) {
            report($th);

            throw new ServerErrorException();
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'You are eligible to participate in this campaign',
            'data' => null
        ]);
    }

    public function enterSubmissionHandler(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|max:2048'
        ], [
            'photo.required' => 'Silakan unggah foto anda',
            'photo.image' => 'File yang di unggah harus berupa gambar',
            'photo.max' => 'Maksimum ukuran file yang diunggah adalah 2Mb'
        ]);
        if ($validator->fails()) {
            throw new BadRequestException($validator->errors()->all()[0]);
        }

        // get the voucher based on the lock_for assigned to this user
        $voucher = Voucher::firstWhere('lock_for', $request->user()->customer_id);
        if ($voucher == null) {
            throw new BadRequestException('You are not eligible in this campaign');
        }

        // if the submission is exceeds 10 mins then unassign the customer for the voucher
        if (Carbon::now()->gt(Carbon::parse($voucher->updated_at)->addMinutes(10))) {
            $voucher->lock_for = null;
            $voucher->save();

            throw new BadRequestException('Submission failed, you must enter submission in less than or equal 10 minutes after eligibility check');
        }

        $verifyPicture = $this->photoVerificationService->verify($request->file('photo'));
        if (!$verifyPicture) {
            throw new BadRequestException('Please upload a photo as required');
        }

        $voucher->given_to = $request->user()->customer_id;
        $voucher->save();

        return response()->json([
            'status' => 'ok',
            'message' => 'Congratulation! enjoy FREE cash to spend',
            'data' => [
                'code' => $voucher->code
            ]
        ]);
    }
}
