import React, { useEffect, useState } from "react";
import { useBookingStore } from "@/store/bookingStore";

interface BookingData {
  id: number;
  status: string;
  space_id: number;
  space_title?: string;
  package_id?: number;
  customer_name: string;
  customer_email: string;
  customer_phone?: string;
  booking_date: string;
  start_time: string;
  end_time: string;
  duration_hours: number;
  total_price: number;
  extras?: Array<{ extra_id: number; quantity: number; extra_name?: string }>;
  notes?: string;
}

export function Step7Confirmation() {
  const bookingStatus = useBookingStore((s) => s.bookingStatus);
  const bookingId = useBookingStore((s) => s.bookingId);
  const reset = useBookingStore((s) => s.reset);
  const [bookingData, setBookingData] = useState<BookingData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  // Fetch full booking data when bookingId is available
  useEffect(() => {
    if (bookingId) {
      fetch(`${window.sbConfig.apiBase}/bookings/${bookingId}`)
        .then((res) => {
          if (!res.ok) throw new Error(`HTTP ${res.status}`);
          return res.json();
        })
        .then((data) => {
          setBookingData(data);
          setLoading(false);
        })
        .catch((err) => {
          console.error("Failed to fetch booking:", err);
          setError("Failed to load booking details.");
          setLoading(false);
        });
    } else {
      setLoading(false);
    }
  }, [bookingId]);

  if (loading) {
    return (
      <div className="sb-step sb-step-7">
        <p>Loading booking details...</p>
      </div>
    );
  }

  if (error || !bookingData) {
    return (
      <div className="sb-step sb-step-7">
        <p className="sb-error">{error || "Booking details not available."}</p>
        <button
          className="sb-btn sb-btn--primary"
          onClick={() => window.location.reload()}
        >
          Retry
        </button>
      </div>
    );
  }

  const formatTimeTo12Hour = (timeStr: string): string => {
    const [hourStr, minuteStr] = timeStr.split(":");
    let hour = parseInt(hourStr, 10);
    const minutes = minuteStr;
    const period = hour >= 12 ? "PM" : "AM";
    hour = hour % 12;
    if (hour === 0) hour = 12;
    return `${hour}:${minutes} ${period}`;
  };

  const getExtraTitle = (extraId: number, extra_name?: string) =>
    extra_name || `Extra #${extraId}`;

  return (
    <div className="sb-step sb-step-7">
      {/* Success banner */}
      <div className="sb-confirm-banner">
        <span className="sb-confirm-icon" aria-hidden="true">
          ✅
        </span>
        <h2 className="sb-confirm-title">
          {bookingStatus === "in_review"
            ? "Payment In Review!"
            : "Booking Created!"}
        </h2>
        <p className="sb-confirm-subtitle">
          {bookingStatus === "in_review"
            ? "Payment successful! A human will review this booking to ensure total accuracy. You can expect a confirmation update from a member of our staff within 24 hours."
            : "You're being redirected to secure payment. A confirmation email will be sent after payment."}
        </p>
      </div>

      {/* Invoice card */}
      <div className="sb-invoice">
        <div className="sb-invoice__header">
          <h3>
            Booking Receipt (
            {bookingStatus === "in_review"
              ? "Paid - In Review"
              : "Pending Payment"}
            )
          </h3>
          {bookingData && (
            <span className="sb-invoice__id">#{bookingData.id}</span>
          )}
        </div>

        <table className="sb-invoice__table">
          <tbody>
            <tr>
              <th>Space</th>
              <td>
                {bookingData.space_title || `Space #${bookingData.space_id}`}
              </td>
            </tr>
            <tr>
              <th>Date</th>
              <td>{bookingData.booking_date}</td>
            </tr>
            <tr>
              <th>Time</th>
              <td>
                {formatTimeTo12Hour(bookingData.start_time)} –{" "}
                {formatTimeTo12Hour(bookingData.end_time)}
              </td>
            </tr>
            <tr>
              <th>Duration</th>
              <td>{Number(bookingData.duration_hours).toFixed(1)} hours</td>
            </tr>
            <tr>
              <th>Name</th>
              <td>{bookingData.customer_name}</td>
            </tr>
            <tr>
              <th>Email</th>
              <td>{bookingData.customer_email}</td>
            </tr>
            {bookingData.customer_phone && (
              <tr>
                <th>Phone</th>
                <td>{bookingData.customer_phone}</td>
              </tr>
            )}
            {bookingData.extras && bookingData.extras.length > 0 && (
              <tr>
                <th>Extras</th>
                <td>
                  <ul className="sb-confirm-extras">
                    {bookingData.extras.map((e) => (
                      <li key={e.extra_id}>
                        {getExtraTitle(e.extra_id, e.extra_name)}
                        {e.quantity > 1 && ` × ${e.quantity}`}
                      </li>
                    ))}
                  </ul>
                </td>
              </tr>
            )}
            <tr className="sb-invoice__total">
              <th>
                {bookingStatus === "in_review" ? "Total Paid" : "Total Due"}
              </th>
              <td>
                {parseFloat(bookingData.total_price.toString()).toFixed(2)}{" "}
                {window.sbConfig.symbol}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      {/* Actions */}
      <div className="sb-confirm-actions">
        <button className="sb-btn sb-btn--primary" onClick={reset}>
          Make Another Booking
        </button>
      </div>

      {bookingStatus === "in_review" && (
        <div
          className="sb-review-notice"
          style={{
            background: "#d4edda",
            padding: "15px",
            borderRadius: "8px",
            margin: "20px 0",
            borderLeft: "4px solid #28a745",
          }}
        >
          <strong>📋 Next Steps:</strong> Our team will review your booking
          within 24 hours and send final confirmation.
        </div>
      )}
      <p className="sb-confirm-lookup">
        {bookingStatus === "in_review"
          ? "Booking in review. Manage via "
          : "You'll receive confirmation after payment. Manage bookings via "}
        <a href="/booking-lookup/">booking lookup</a> with your email.
      </p>
    </div>
  );
}
