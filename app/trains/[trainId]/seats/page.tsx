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

  // Resolve params and searchParams to ensure they are always defined
  const resolvedParams = {
    trainId: params?.trainId || "undefined",
  };
  const resolvedSearchParams = {
    from: searchParams?.from || undefined,
    to: searchParams?.to || undefined,
    trainName: searchParams?.trainName || undefined,
    coach: searchParams?.coach || undefined,
  };

  useEffect(() => {
    async function fetchTrainData() {
      try {
        setLoading(true);
        setError(null);

        if (!resolvedParams.trainId || resolvedParams.trainId === "undefined") {
          throw new Error("Train ID is missing or invalid");
        }

        const url = new URL(
          `/api/trains/${resolvedParams.trainId}/detail`,
          window.location.origin,
        );
        if (resolvedSearchParams.from) url.searchParams.set("from", resolvedSearchParams.from);
        if (resolvedSearchParams.to) url.searchParams.set("to", resolvedSearchParams.to);

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

    if (resolvedParams && resolvedSearchParams) {
      fetchTrainData();
    }
  }, [resolvedParams, resolvedSearchParams]);

  if (!resolvedParams || !resolvedSearchParams || loading) {
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

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50">
      <SeatDirectionViewer
        trainId={resolvedParams.trainId}
        trainName={resolvedSearchParams.trainName || trainData?.name || "Unknown Train"}
        trainNumber={resolvedParams.trainId}
        from={resolvedSearchParams.from || "Unknown"}
        to={resolvedSearchParams.to || "Unknown"}
        filterCoach={resolvedSearchParams.coach}
        trainData={trainData}
      />
    </div>
  );
}