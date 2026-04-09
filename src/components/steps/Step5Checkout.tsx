import React, { useState } from 'react';
import {
  Elements,
  PaymentElement,
  useStripe,
  useElements,
} from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { useBookingStore } from '@/store/bookingStore';
import { createBooking } from '@/utils/api';

// Lazily initialize Stripe
let stripePromise: ReturnType<typeof loadStripe> | null = null;
const getStripe = () => {
  if (!stripePromise) {
    stripePromise = loadStripe(window.sbConfig.stripeKey);
  }
  return stripePromise;
};

// ── Inner form (needs Stripe context) ────────────────────────────────────────

function CheckoutForm() {
  const stripe   = useStripe();
  const elements = useElements();

  const {
    priceBreakdown, totalPrice,
    selectedSpace, selectedPackage,
    selectedDate, selectedStartTime, selectedEndTime,
    selectedExtras, customerInfo,
    clientSecret, setCheckoutData,
    confirmBooking, nextStep, prevStep,
  } = useBookingStore();

  const [loading, setLoading] = useState(false);
  const [error,   setError]   = useState('');
  const [step, setStep] = useState<'summary' | 'pay'>('summary');

  // ── Create booking + PaymentIntent ───────────────────────────────────────
  const handleInitiatePay = async () => {
    if (clientSecret) { setStep('pay'); return; } // already created

    const spaceId   = selectedSpace?.id ?? selectedPackage?.space_id;
    const packageId = selectedPackage?.id;

    if (!spaceId) { setError('No space selected.'); return; }

    setLoading(true);
    setError('');

    try {
      const res = await createBooking({
        space_id:        spaceId,
        package_id:      packageId,
        date:            selectedDate,
        start_time:      selectedStartTime,
        end_time:        selectedEndTime,
        customer_name:   customerInfo.name,
        customer_email:  customerInfo.email,
        customer_phone:  customerInfo.phone,
        notes:           customerInfo.notes,
        extras:          selectedExtras,
      });

      setCheckoutData({
        clientSecret: res.client_secret,
        bookingId:    res.booking_id,
        totalPrice:   res.total_price,
        breakdown:    res.breakdown,
      });

      setStep('pay');
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setLoading(false);
    }
  };

  // ── Confirm payment ───────────────────────────────────────────────────────
  const handlePay = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!stripe || !elements) return;

    setLoading(true);
    setError('');

    const { error: stripeError } = await stripe.confirmPayment({
      elements,
      confirmParams: {
        return_url: window.location.href, // not actually used — webhook confirms
      },
      redirect: 'if_required',
    });

    if (stripeError) {
      setError(stripeError.message ?? 'Payment failed.');
      setLoading(false);
      return;
    }

    // Payment succeeded — webhook will confirm in DB; we show confirmation
    confirmBooking();
    nextStep();
    setLoading(false);
  };

  if (step === 'summary') {
    return (
      <div className="sb-checkout-summary">
        <h3>Booking Summary</h3>

        <div className="sb-summary-grid">
          <div className="sb-summary-row">
            <span>Space</span>
            <span>{selectedSpace?.title ?? selectedPackage?.space_name}</span>
          </div>
          <div className="sb-summary-row">
            <span>Date</span>
            <span>{selectedDate}</span>
          </div>
          <div className="sb-summary-row">
            <span>Time</span>
            <span>{selectedStartTime} – {selectedEndTime}</span>
          </div>
          <div className="sb-summary-row">
            <span>Name</span>
            <span>{customerInfo.name}</span>
          </div>
          <div className="sb-summary-row">
            <span>Email</span>
            <span>{customerInfo.email}</span>
          </div>
        </div>

        <h4>Price Breakdown</h4>
        <ul className="sb-breakdown">
          {priceBreakdown.map((item, i) => (
            <li key={i} className="sb-breakdown__item">
              <span>{item.label}</span>
              <span>${item.amount.toFixed(2)}</span>
            </li>
          ))}
        </ul>
        <div className="sb-breakdown__total">
          Total Due: <strong>${totalPrice.toFixed(2)}</strong>
        </div>

        {error && <div className="sb-error">{error}</div>}

        <div className="sb-step__actions">
          <button className="sb-btn sb-btn--ghost" onClick={prevStep}>← Back</button>
          <button
            className="sb-btn sb-btn--primary"
            onClick={handleInitiatePay}
            disabled={loading}
          >
            {loading ? 'Preparing…' : 'Pay Now →'}
          </button>
        </div>
      </div>
    );
  }

  return (
    <form onSubmit={handlePay} className="sb-payment-form">
      <h3>Enter Payment Details</h3>
      <PaymentElement />

      {error && <div className="sb-error sb-error--mt">{error}</div>}

      <div className="sb-step__actions">
        <button type="button" className="sb-btn sb-btn--ghost" onClick={() => setStep('summary')}>
          ← Back
        </button>
        <button
          type="submit"
          className="sb-btn sb-btn--primary"
          disabled={!stripe || !elements || loading}
        >
          {loading ? 'Processing…' : `Pay $${totalPrice.toFixed(2)}`}
        </button>
      </div>
    </form>
  );
}

// ── Outer wrapper (provides Stripe Elements context) ─────────────────────────

export function Step5Checkout() {
  const { clientSecret } = useBookingStore();

  // If we already have a clientSecret, wrap in Elements immediately
  if (clientSecret) {
    return (
      <div className="sb-step sb-step-5">
        <h2 className="sb-step__title">Review & Payment</h2>
        <Elements stripe={getStripe()} options={{ clientSecret }}>
          <CheckoutForm />
        </Elements>
      </div>
    );
  }

  // Otherwise, render summary form without Elements
  // (Elements will be added after booking is created and clientSecret is returned)
  return (
    <div className="sb-step sb-step-5">
      <h2 className="sb-step__title">Review & Payment</h2>
      <Elements stripe={getStripe()}>
        <CheckoutForm />
      </Elements>
    </div>
  );
}
