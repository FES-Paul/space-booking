import React from "react";
import { useBookingStore } from "@/store/bookingStore";

export function Step6Confirmation() {
  const {
    customerInfo,
    selectedSpace,
    selectedPackage,
    selectedDate,
    selectedStartTime,
    selectedEndTime,
    totalPrice,
    selectedExtras,
    availableExtras,
    bookingId,
    reset,
  } = useBookingStore();

  const spaceName =
    selectedSpace?.title ?? selectedPackage?.space_name ?? "Space";

  const getExtraTitle = (extraId: number) =>
    availableExtras.find((e) => e.id === extraId)?.title ?? `Extra #${extraId}`;

  return (
    <div className="sb-step sb-step-6">
      {/* Success banner */}
      <div className="sb-confirm-banner">
        <span className="sb-confirm-icon" aria-hidden="true">
          ✅
        </span>
        <h2 className="sb-confirm-title">Booking Confirmed!</h2>
        <p className="sb-confirm-subtitle">
          A confirmation email has been sent to{" "}
          <strong>{customerInfo.email}</strong>.
        </p>
      </div>

      {/* Invoice card */}
      <div className="sb-invoice">
        <div className="sb-invoice__header">
          <h3>Booking Receipt</h3>
          {bookingId && <span className="sb-invoice__id">#{bookingId}</span>}
        </div>

        <table className="sb-invoice__table">
          <tbody>
            <tr>
              <th>Space</th>
              <td>{spaceName}</td>
            </tr>
            <tr>
              <th>Date</th>
              <td>{selectedDate}</td>
            </tr>
            <tr>
              <th>Time</th>
              <td>
                {selectedStartTime} – {selectedEndTime}
              </td>
            </tr>
            <tr>
              <th>Name</th>
              <td>{customerInfo.name}</td>
            </tr>
            <tr>
              <th>Email</th>
              <td>{customerInfo.email}</td>
            </tr>
            {customerInfo.phone && (
              <tr>
                <th>Phone</th>
                <td>{customerInfo.phone}</td>
              </tr>
            )}
            {selectedExtras.length > 0 && (
              <tr>
                <th>Extras</th>
                <td>
                  <ul className="sb-confirm-extras">
                    {selectedExtras.map((e) => (
                      <li key={e.extra_id}>
                        {getExtraTitle(e.extra_id)}
                        {e.quantity > 1 && ` × ${e.quantity}`}
                      </li>
                    ))}
                  </ul>
                </td>
              </tr>
            )}
            <tr className="sb-invoice__total">
              <th>Total Paid</th>
              <td>
                {window.sbConfig.symbol}
                {totalPrice.toFixed(2)}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      {/* Actions */}
      <div className="sb-confirm-actions">
        <button className="sb-btn sb-btn--ghost" onClick={() => window.print()}>
          🖨 Print Receipt
        </button>
        <button className="sb-btn sb-btn--primary" onClick={reset}>
          Make Another Booking
        </button>
      </div>

      <p className="sb-confirm-lookup">
        Need to view or manage your bookings?{" "}
        <a href="/booking-lookup/">Use our booking lookup</a> with your email.
      </p>
    </div>
  );
}
