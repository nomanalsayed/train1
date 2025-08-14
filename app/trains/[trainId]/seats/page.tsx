"use client";

import { SeatDirectionViewer } from "@/components/seat-direction-viewer";
import { Button } from "@/components/ui/button";
import { Home, Loader2 } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

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

        const url = new URL(
          `/api/trains/${params.trainId}/detail`,
          window.location.origin,
        );
        if (searchParams.from) url.searchParams.set("from", searchParams.from);
        if (searchParams.to) url.searchParams.set("to", searchParams.to);

        const response = await fetch(url.toString());

        if (!response.ok) {
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

    if (params.trainId && params.trainId !== "undefined") {
      fetchTrainData();
    } else {
      setLoading(false);
      setError(
        "Train ID is missing. Please select a train from the search page.",
      );
    }
  }, [params.trainId, searchParams.from, searchParams.to]);

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50 flex items-center justify-center px-4">
        <div className="text-center bg-white rounded-2xl p-8 shadow-lg border border-emerald-100">
          <Loader2 className="w-10 h-10 animate-spin text-emerald-600 mx-auto mb-4" />
          <p className="text-gray-600 font-medium">Loading train data...</p>
          <p className="text-sm text-gray-400 mt-2">
            Please wait while we fetch seat information
          </p>
        </div>
      </div>
    );
  }

  if (error || !trainData) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-red-50 to-orange-50 flex items-center justify-center px-4">
        <div className="text-center max-w-md mx-auto bg-white rounded-2xl p-8 shadow-lg border border-red-100">
          <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <Home className="w-8 h-8 text-red-600" />
          </div>
          <h2 className="text-xl font-semibold text-gray-900 mb-3">
            Oops! Something went wrong
          </h2>
          <p className="text-red-600 mb-6 text-sm">
            {error || "Train data not found"}
          </p>
          <Button
            onClick={() => router.push("/")}
            variant="outline"
            className="w-full bg-white hover:bg-gray-50 border-2 border-emerald-200 text-emerald-700 font-medium py-3"
          >
            <Home className="w-4 h-4 mr-2" />
            Back to Home
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50">
      <SeatDirectionViewer
        trainId={params.trainId}
        trainName={searchParams.trainName || trainData.name}
        trainNumber={params.trainId}
        from={searchParams.from || trainData.fromStation?.title}
        to={searchParams.to || trainData.toStation?.title}
        filterCoach={searchParams.coach}
        trainData={trainData}
      />
    </div>
  );
}
