
"use client";

import { Button } from "@/components/ui/button";
import { Home, Loader2 } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

// Import the actual SeatMapVisual component
import SeatMapVisual from "@/components/seat-map-visual";

interface PageProps {
  params: {
    trainId: string;
  };
  searchParams: {
    from?: string;
    to?: string;
    trainName?: string;
    coach?: string;
  };
}

interface CoachData {
  coach_id: number;
  coach_code: string;
  type: string;
  class_name: string;
  total_seats: number;
  position: number;
  seat_layout?: any[][];
  direction?: string;
  route_code?: string;
}

interface TrainData {
  id: number;
  name: string;
  train_name: string;
  from_station: string;
  to_station: string;
  code_from_to: string;
  code_to_from: string;
  coaches: CoachData[];
  routes?: any[];
  train_classes?: any[];
}

export default function SeatMapPage({ params, searchParams }: PageProps) {
  const [trainData, setTrainData] = useState<TrainData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const router = useRouter();

  useEffect(() => {
    async function fetchTrainData() {
      try {
        setLoading(true);
        setError(null);

        if (!params.trainId || params.trainId === "undefined") {
          throw new Error("Train ID is missing or invalid");
        }

        console.log("Fetching coaches from API...");
        
        const url = new URL(
          `/api/trains/${params.trainId}/detail`,
          window.location.origin,
        );
        if (searchParams.from) url.searchParams.set("from", searchParams.from);
        if (searchParams.to) url.searchParams.set("to", searchParams.to);

        const response = await fetch(url.toString());
        console.log("Coaches API response status:", response.status);

        if (!response.ok) {
          throw new Error(`Failed to fetch train data: ${response.status}`);
        }

        const data = await response.json();
        console.log("Coaches API data:", data);
        
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

    fetchTrainData();
  }, [params.trainId, searchParams.from, searchParams.to]);

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

  if (!trainData || !trainData.train_classes || trainData.train_classes.length === 0) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50 flex items-center justify-center">
        <div className="text-center max-w-md mx-auto p-6">
          <div className="text-gray-600 mb-4">
            <h2 className="text-xl font-semibold">No Coach Information Available</h2>
            <p className="text-sm mt-2">No coach data available for this train or route.</p>
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

  // Extract coaches from train_classes
  const allCoaches: CoachData[] = [];
  trainData.train_classes.forEach((trainClass) => {
    if (trainClass.coaches && Array.isArray(trainClass.coaches)) {
      trainClass.coaches.forEach((coach: any) => {
        allCoaches.push({
          coach_id: coach.coach_id,
          coach_code: coach.coach_code,
          type: trainClass.class_short || 'UNKNOWN',
          class_name: trainClass.class_name || 'Unknown Class',
          total_seats: coach.total_seats || 50,
          position: allCoaches.length + 1,
          seat_layout: coach.seat_layout || [],
          direction: coach.direction || 'forward',
          route_code: coach.route_code || trainData.code_from_to,
        });
      });
    }
  });

  if (allCoaches.length === 0) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50 flex items-center justify-center">
        <div className="text-center max-w-md mx-auto p-6">
          <div className="text-gray-600 mb-4">
            <h2 className="text-xl font-semibold">No Coach Information Available</h2>
            <p className="text-sm mt-2">No coaches found for this train.</p>
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

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50 p-4 md:p-8">
      <SeatMapVisual
        coach={allCoaches[0]} // Display the first coach
        trainName={trainData.train_name || searchParams.trainName || "Unknown Train"}
        route={{
          from: searchParams.from || trainData.from_station || "Unknown",
          to: searchParams.to || trainData.to_station || "Unknown",
        }}
      />
    </div>
  );
}
