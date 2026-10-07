<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateRcaOfferRequest;
use App\Http\Requests\CreateRcaPolicyRequest;
use App\Services\RcaApiClient;
use App\Services\RcaAuditLogger;
use App\Services\RcaCalculationRepository;
use App\Services\RcaQuotationPayloadFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RcaCalculatorController extends Controller
{
    public function __construct(private RcaCalculationRepository $calculations, private RcaAuditLogger $audit) {}

    public function index(): View
    {
        return view('rca.index', ['calculations' => $this->calculations->latest()]);
    }

    public function offer(CreateRcaOfferRequest $request, RcaApiClient $rcaApi, RcaQuotationPayloadFactory $payloads): RedirectResponse
    {
        $payload = $payloads->offer($request->validated());
        $id = $this->calculations->create($request->string('insurer')->toString(), $this->audit->redactSecrets($payload));
        $this->audit->log($request, $id, 'offer_requested', $this->audit->redactSecrets($payload));
        try {
            $response = $rcaApi->createOffer($payload);
            $this->calculations->saveOffer($id, $response);
            $this->audit->log($request, $id, 'offer_received', $this->audit->redactSecrets($response));
        } catch (RequestException|ConnectionException $exception) {
            $this->calculations->markOfferFailed($id);
            $this->audit->log($request, $id, 'offer_failed', ['message' => $exception->getMessage()]);

            return back()->withInput()->withErrors(['rca' => 'Oferta nu a putut fi obtinuta: '.$exception->getMessage()]);
        }

        return to_route('rca.index')->with('success', 'Oferta a fost obtinuta si inregistrata in jurnal.');
    }

    public function policy(CreateRcaPolicyRequest $request, int $calculation, RcaApiClient $rcaApi, RcaQuotationPayloadFactory $payloads): RedirectResponse
    {
        $record = $this->calculations->find($calculation);
        abort_unless($record && $record->offer_id, 404);
        $payload = $payloads->policy($record->offer_id, $request->validated());
        $this->audit->log($request, $calculation, 'policy_requested', $this->audit->redactSecrets($payload));
        try {
            $response = $rcaApi->createPolicy($payload);
            $this->calculations->savePolicy($calculation, $response);
            $this->audit->log($request, $calculation, 'policy_received', $this->audit->redactSecrets($response));
        } catch (RequestException|ConnectionException $exception) {
            $this->calculations->markPolicyFailed($calculation);
            $this->audit->log($request, $calculation, 'policy_failed', ['message' => $exception->getMessage()]);

            return back()->withErrors(['rca' => 'Polita nu a putut fi emisa: '.$exception->getMessage()]);
        }

        return to_route('rca.index')->with('success', 'Polita a fost emisa. PDF-ul este disponibil mai jos.');
    }

    public function pdf(Request $request, int $calculation, string $type, RcaApiClient $rcaApi): Response
    {
        $record = $this->calculations->find($calculation);
        abort_unless($record && in_array($type, ['offer', 'policy'], true), 404);
        $id = $type === 'offer' ? $record->offer_id : $record->policy_id;
        abort_unless($id, 404);
        $response = $type === 'offer' ? $rcaApi->offerPdf($id) : $rcaApi->policyPdf($id);
        $file = data_get($response, 'data.files.0');
        abort_unless(is_array($file) && isset($file['content']), 404);
        $this->audit->log($request, $calculation, "{$type}_pdf_downloaded", ['name' => $file['name'] ?? null]);

        return response(base64_decode($file['content'], true), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.($file['name'] ?? "{$type}.pdf").'"']);
    }
}
