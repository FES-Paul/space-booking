import React, { useEffect, useState } from "react";
import { useBookingStore } from "@/store/bookingStore";
import { fetchExtras, fetchPricing } from "@/utils/api";
import type {
  Extra,
  PriceBreakdownItem,
  PricingResponse,
  SelectedExtra,
  Space,
  Package,
  SelectionItem,
} from "@/types";

interface EnrichedBreakdownItem {
  label: string;
  amount: number;
}

export function Step3Addons() {
  const {
    selectedItems,
    selectedDate,
    selectedStartTime,
    selectedEndTime,
    selectedExtras,
    availableExtras,
    toggleExtra,
    setAvailableExtras,
    setPriceBreakdown,
    nextStep,
    prevStep,
    getPrimarySpaceId,
  } = useBookingStore();

  const spaceId = getPrimarySpaceId() ?? 0;
  const pkgItem = selectedItems.find(
    (item: SelectionItem) => item.type === "package",
  ) as Package | undefined;
  const packageId = pkgItem?.id;
  const primarySpace = selectedItems.find(
    (item: SelectionItem) => item.type === "space",
  ) as Space | undefined;

  // Entry logging AFTER state is available
  console.group("🚀 STEP3 ADDONS - Entry Props");
  console.log("spaceId:", spaceId);
  console.log("selectedDate:", selectedDate);
  console.log("selectedStartTime:", selectedStartTime);
  console.log("selectedEndTime:", selectedEndTime);
  console.log("pkgItem:", pkgItem);
  console.log("primarySpace:", primarySpace);
  console.log("selectedExtras:", selectedExtras);
  console.groupEnd();

  const [extras, setExtras] = useState<Extra[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [preview, setPreview] = useState<{
    total: number;
    breakdown: EnrichedBreakdownItem[];
  } | null>(null);

  useEffect(() => {
    if (!spaceId || !selectedDate || !selectedStartTime || !selectedEndTime) {
      console.log("STEP3: Missing required params, skipping fetchExtras");
      return;
    }

    console.group("📦 STEP3 fetchExtras");
    console.log("Params:", {
      spaceId,
      selectedDate,
      selectedStartTime,
      selectedEndTime,
    });

    setLoading(true);
    fetchExtras(spaceId, selectedDate, selectedStartTime, selectedEndTime)
      .then((data) => {
        console.log("✅ fetchExtras RAW RESPONSE:", data);
        console.log("Extras count:", data.length);
        if (data.length === 0) {
          console.warn("⚠️ Backend returned EMPTY extras array!");
        }
        setExtras(data);
        setAvailableExtras(data);
        console.groupEnd();
      })
      .catch((e: Error) => {
        console.error("❌ fetchExtras ERROR:", e.message);
        setError(e.message);
        console.groupEnd();
      })
      .finally(() => setLoading(false));
  }, [
    spaceId,
    selectedDate,
    selectedStartTime,
    selectedEndTime,
    setAvailableExtras,
  ]);

  // Re-calculate price whenever extras selection changes
  useEffect(() => {
    if (!spaceId || !selectedDate || !selectedStartTime || !selectedEndTime) {
      console.log("STEP3: Missing params, skipping fetchPricing");
      return;
    }

    console.group("💰 STEP3 fetchPricing");
    const pricingParams = {
      space_id: spaceId,
      date: selectedDate,
      start_time: selectedStartTime,
      end_time: selectedEndTime,
      extras: selectedExtras,
      package_id: packageId,
    };
    console.log("Params sent to /pricing:", pricingParams);

    fetchPricing(pricingParams)
      .then((res: PricingResponse) => {
        console.log("✅ fetchPricing FULL RESPONSE:", res);
        console.log("Total:", res.total_price);
        console.log("Breakdown:", res.breakdown);
        const enrichedBreakdown: EnrichedBreakdownItem[] = res.breakdown.map(
          (item: PriceBreakdownItem) => {
            let label = item.label;
            if (label === "Package price" && pkgItem) {
              label = `${pkgItem.title}`;
            } else if (label === "Extras" && selectedExtras.length > 0) {
              const extraNames = selectedExtras
                .map((e: SelectedExtra) => {
                  const extra = availableExtras.find(
                    (ex: Extra) => ex.id === e.extra_id,
                  );
                  return extra ? extra.title : `Extra #${e.extra_id}`;
                })
                .join(" + ");
              label = `Extras: ${extraNames}`;
            } else if (
              primarySpace &&
              (label.includes("–") ||
                label.match(/^\\d{2}:\\d{2}–\\d{2}:\\d{2}/))
            ) {
              label = `${primarySpace.title}: ${label}`;
            }
            return { label, amount: item.amount };
          },
        );
        setPreview({ total: res.total_price, breakdown: enrichedBreakdown });
        setPriceBreakdown(
          enrichedBreakdown as PriceBreakdownItem[],
          res.total_price,
        );
        console.groupEnd();
      })
      .catch((error) => {
        console.error("❌ fetchPricing ERROR:", error);
        console.groupEnd();
      });
  }, [
    selectedExtras,
    availableExtras,
    primarySpace,
    pkgItem,
    spaceId,
    selectedDate,
    selectedStartTime,
    selectedEndTime,
    packageId,
  ]);

  const isSelected = (id: number) =>
    selectedExtras.some((e) => e.extra_id === id);

  return (
    <div className="sb-step sb-step-3">
      <h2 className="sb-step__title">Add-ons & Extras</h2>

      {loading && <div className="sb-loading">Loading extras…</div>}
      {error && <div className="sb-error">{error}</div>}

      {!loading && extras.length === 0 && (
        <p className="sb-empty">No extras available for this time slot.</p>
      )}

      {!loading && extras.length > 0 && (
        <div className="sb-extras">
          {extras.map((extra) => (
            <div
              key={extra.id}
              className={`sb-extra-card ${isSelected(extra.id) ? "sb-extra-card--selected" : ""} ${!extra.is_available ? "sb-extra-card--unavailable" : ""}`}
            >
              <div className="sb-extra-card__info">
                {extra.thumbnail && (
                  <img
                    src={extra.thumbnail}
                    alt={extra.title}
                    className="sb-extra-card__img"
                  />
                )}
                <div>
                  <strong className="sb-extra-card__name">{extra.title}</strong>
                  <p className="sb-extra-card__desc">{extra.description}</p>
                  <span className="sb-extra-card__price">
                    {window.sbConfig.symbol}
                    {extra.price.toFixed(2)}
                  </span>
                  {!extra.is_available && extra.unavailable_reason && (
                    <span
                      className={`sb-badge sb-badge--sold-out ${
                        extra.unavailable_reason === "space_override"
                          ? "sb-badge--closed"
                          : ""
                      }`}
                    >
                      {extra.unavailable_reason === "space_override"
                        ? "Closed this time"
                        : "Sold Out"}
                    </span>
                  )}
                  {extra.is_available && extra.available_qty < 3 && (
                    <span className="sb-badge sb-badge--low">
                      Only {extra.available_qty} left
                    </span>
                  )}
                </div>
              </div>
              <button
                className={`sb-btn ${isSelected(extra.id) ? "sb-btn--danger" : "sb-btn--secondary"}`}
                disabled={!extra.is_available}
                onClick={() => {
                  console.group("🔄 STEP3 Toggle Extra");
                  console.log("Toggling extra ID:", extra.id);
                  console.log("Current selectedExtras:", selectedExtras);
                  toggleExtra(extra.id);
                  console.log("After toggle - should trigger pricing refetch");
                  console.groupEnd();
                }}
              >
                {isSelected(extra.id) ? "Remove" : "Add"}
              </button>
            </div>
          ))}
        </div>
      )}

      {/* Live price preview */}
      {preview && (
        <div className="sb-price-preview">
          <h4>Price Preview</h4>
          <ul className="sb-breakdown">
            {preview.breakdown.map((item, i) => (
              <li key={i} className="sb-breakdown__item">
                <span>{item.label}</span>
                <span>
                  {window.sbConfig.symbol}
                  {item.amount.toFixed(2)}
                </span>
              </li>
            ))}
          </ul>
          <div className="sb-breakdown__total">
            Total:{" "}
            <strong>
              {window.sbConfig.symbol}
              {preview.total.toFixed(2)}
            </strong>
          </div>
        </div>
      )}

      <div className="sb-step__actions">
        <button className="sb-btn sb-btn--ghost" onClick={prevStep}>
          ← Back
        </button>
        <button className="sb-btn sb-btn--primary" onClick={nextStep}>
          Continue →
        </button>
      </div>
    </div>
  );
}
