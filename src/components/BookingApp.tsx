import React, { useEffect } from "react";
import { useBookingStore } from "@/store/bookingStore";
import { fetchSpace, fetchPackages, checkCartHasBooking } from "@/utils/api";
import { StepProgress } from "./shared/StepProgress";
import { Step1Selection } from "./steps/Step1Selection";
import { Step2Scheduling } from "./steps/Step2Scheduling";
import { Step3Addons } from "./steps/Step3Addons";
import { Step4Details } from "./steps/Step4Details";
import { Step5Checkout } from "./steps/Step5Checkout";
import { Step5Terms } from "./steps/Step5Terms";
import { Step6Payment } from "./steps/Step6Payment";
import { Step7Confirmation } from "./steps/Step7Confirmation";

interface Props {
  preSpaceId?: number;
  prePackageId?: number;
}

export function BookingApp({ preSpaceId, prePackageId }: Props) {
  const { setSpace, setPackage, setStep, bookingId } = useBookingStore();
  const currentStep = useBookingStore((s) => s.currentStep);

  // Cart/session check + cleanup
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get("step") === "7" && bookingId) {
      return; // Direct confirmation
    }

    const initCartCheck = async () => {
      try {
        const res = await checkCartHasBooking();
        if (res.hasCartBooking) {
          window.location.href = "/checkout/";
          return;
        } else {
          // Cart empty → clear persisted state
          useBookingStore.getState().clearPersistedState();
        }
      } catch (e) {
        console.error("Cart check on app init failed:", e);
        // Assume empty on error → clear state
        useBookingStore.getState().clearPersistedState();
      }
    };

    initCartCheck();
  }, []);

  // Pre-select space or package from shortcode attributes
  useEffect(() => {
    if (preSpaceId) {
      fetchSpace(preSpaceId)
        .then((space) => {
          setSpace(space);
          setStep(2);
        })
        .catch(() => {
          /* ignore */
        });
    } else if (prePackageId) {
      fetchPackages()
        .then((pkgs) => {
          const pkg = pkgs.find((p) => p.id === prePackageId);
          if (pkg) {
            setPackage(pkg);
            setStep(2);
          }
        })
        .catch(() => {
          /* ignore */
        });
    }
  }, [preSpaceId, prePackageId]);

  return (
    <div className="sb-app">
      <StepProgress currentStep={currentStep} />

      <div className="sb-step-container">
        {currentStep === 1 && <Step1Selection />}
        {currentStep === 2 && <Step2Scheduling />}
        {currentStep === 3 && <Step3Addons />}
        {currentStep === 4 && <Step4Details />}
        {currentStep === 5 && <Step5Terms />}
        {currentStep === 6 && <Step6Payment />}
        {currentStep === 7 && <Step7Confirmation />}
      </div>
    </div>
  );
}
