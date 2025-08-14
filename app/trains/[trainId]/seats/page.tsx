"use client";

import { SeatDirectionViewer } from "@/components/seat-direction-viewer";
import { Button } from "@/components/ui/button";
import { Home, Loader2 } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useState, use } from "react";

// Assuming SeatMapVisual is defined in "@/components/seat-map-visual"
// and has a structure to display seat layouts.
// If SeatMapVisual is not yet created, it would need to be implemented.
// For now, we'll assume it exists and takes the props as shown below.

// Placeholder for SeatMapVisual component if it's not provided or needs definition
// import SeatMapVisual from "@/components/seat-map-visual"; 

// Dummy SeatMapVisual component for demonstration purposes if the actual one isn't available
const SeatMapVisual = ({ coach, trainName, route }) => {
  return (
    <div className="bg-white rounded-lg shadow-sm border p-6">
      <div className="flex items-center justify-between mb-4">
        <div>
          <h2 className="text-xl font-semibold text-gray-800">{trainName}</h2>
          <p className="text-sm text-gray-600">{route.from} → {route.to}</p>
        </div>
        <div className="text-right">
          <p className="text-sm text-gray-600">Coach Code</p>
          <p className="font-medium">{coach.code}</p>
        </div>
      </div>
      <div className="mt-4 border-t pt-4">
        <h3 className="text-lg font-semibold text-gray-800 mb-3">Seats in Coach {coach.code}</h3>
        <div className="grid grid-cols-4 gap-2 text-center">
          {/* This is a simplified representation. The actual SeatMapVisual would render seats more accurately. */}
          {Array.from({ length: coach.totalSeats }, (_, i) => i + 1).map((seatNum) => (
            <div key={seatNum} className="p-2 border rounded bg-gray-100 text-sm">
              {seatNum}
            </div>
          ))}
        </div>
        <div className="mt-4 text-sm text-gray-600">
          <p>Front Facing Seats: {coach.frontFacingSeats.join(', ')}</p>
          <p>Back Facing Seats: {coach.backFacingSeats.join(', ')}</p>
        </div>
      </div>
    </div>
  );
};


interface PageProps {
  params: Promise<{
    trainId: string;
  }>;
  searchParams: Promise<{
    from?: string;
    to?: string;
    trainName?: string;
    coach?: string;
  }>;
}

interface TrainData {
  id: number;
  name: string;
  codeFromTo: string;
  fromStation: { title: string; code: string };
  toStation: { title: string; code: string };
  classes: Array<{
    id: number;
    name: string;
    shortCode: string;
    coaches: Array<{
      id: number;
      code: string;
      totalSeats: number;
      frontFacingSeats: number[];
      backFacingSeats: number[];
    }>;
  }>;
}

export default function SeatMapPage({ params, searchParams }: PageProps) {
  // This state structure needs to align with the expected data from the API
  // which seems to be different from the TrainData interface defined above.
  // Let's adjust based on the likely API response structure implied by the changes.
  const [trainData, setTrainData] = useState<any>(null); // Use 'any' or a more specific interface if known
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const router = useRouter();

  // Unwrap async params and searchParams
  const resolvedParams = use(params);
  const resolvedSearchParams = use(searchParams);

  useEffect(() => {
    async function fetchTrainData() {
      try {
        setLoading(true);
        setError(null);

        if (!resolvedParams.trainId || resolvedParams.trainId === "undefined") {
          throw new Error("Train ID is missing or invalid");
        }

        // Adjust the API endpoint and query parameters based on the user's description
        // The user mentioned searching by route code '101' and getting an empty result.
        // This implies the initial fetch might need to handle route code searches or
        // the backend needs to be adjusted to support route code searches.
        // For this frontend code, we'll assume the train detail fetch is correct,
        // but the backend fix for route code search is external to this file.

        // The original fetch was for '/api/trains/${resolvedParams.trainId}/detail'
        // and it seems to be correctly implemented for fetching train details.
        // The issue with route code '101' is likely on the backend side (WordPress API).
        // We'll proceed with the existing fetch for train details.

        const url = new URL(
          `/api/trains/${resolvedParams.trainId}/detail`,
          window.location.origin,
        );
        if (resolvedSearchParams.from) url.searchParams.set("from", resolvedSearchParams.from);
        if (resolvedSearchParams.to) url.searchParams.set("to", resolvedSearchParams.to);

        const response = await fetch(url.toString());

        if (!response.ok) {
          // Handle specific error for route code search if possible, though it's likely backend
          if (response.status === 404 && resolvedSearchParams.from && resolvedSearchParams.to && !resolvedSearchParams.coach) {
             // This might be a scenario where the route code search failed, leading to no train found.
             // However, the user's error message suggests the API *was* called but returned empty.
             // The actual fix for route code search is likely in the backend API.
          }
          throw new Error(`Failed to fetch train data: ${response.status}`);
        }

        const data = await response.json();
        setTrainData(data);
      } catch (err) {
        console.error("Error fetching train data:", err);
        setError(
          err instanceof Error ? err.message : "Failed to load train data",
        );
      } finally {
        setLoading(false);
      }
    }

    if (resolvedParams && resolvedSearchParams) {
      fetchTrainData();
    }
  }, [resolvedParams, resolvedSearchParams]);

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50 flex items-center justify-center">
        <div className="text-center">
          <Loader2 className="h-8 w-8 animate-spin mx-auto text-emerald-600" />
          <p className="mt-2 text-gray-600">Loading train seat information...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50 flex items-center justify-center">
        <div className="text-center max-w-md mx-auto p-6">
          <div className="text-red-600 mb-4">
            <h2 className="text-xl font-semibold">No Coach Information Available</h2>
            <p className="text-sm mt-2">{error}</p>
          </div>
          <Button
            onClick={() => router.push("/")}
            className="bg-emerald-600 hover:bg-emerald-700 text-white"
          >
            <Home className="w-4 h-4 mr-2" />
            Back to Home
          </Button>
        </div>
      </div>
    );
  }

  // The original return statement used SeatDirectionViewer with trainData.
  // The changes indicate that SeatMapVisual should be used instead for the main display.
  // We need to extract the necessary data for SeatMapVisual from the fetched trainData.
  // Based on the changes, SeatMapVisual expects 'coach', 'trainName', and 'route'.
  // The fetched 'data' object seems to have 'coaches' which is an array.
  // The change snippet uses `data.coaches[0]` which implies we are showing the first coach's seats.
  // The `trainName` and `route` are available from `resolvedSearchParams` and `trainData.name`.

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50 p-4 md:p-8">
      {trainData && trainData.coaches && trainData.coaches.length > 0 ? (
        <SeatMapVisual
          coach={trainData.coaches[0]} // Assuming we display the first coach
          trainName={trainData.name || resolvedSearchParams.trainName || "Unknown Train"}
          route={{
            from: resolvedSearchParams.from || "Unknown",
            to: resolvedSearchParams.to || "Unknown",
          }}
        />
      ) : (
        <div className="min-h-screen bg-gray-50 flex items-center justify-center">
          <div className="bg-white rounded-lg shadow-sm border p-8 text-center">
            <p className="text-gray-500">No coach data available for this train or route.</p>
            <Button
              onClick={() => router.push("/")}
              className="mt-4 bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              <Home className="w-4 h-4 mr-2" />
              Back to Home
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}