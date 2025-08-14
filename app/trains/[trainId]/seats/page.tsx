"use client";

import { Button } from "@/components/ui/button";
import { Home, Loader2 } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

// Import the actual SeatMapVisual component
import SeatMapVisual from "@/components/seat-map-visual";
import { ApiClient } from "@/lib/api-client";



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

interface CoachData {
  coach_id: number;
  coach_code: string;
  type: string;
  class_name: string;
  total_seats: number;
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
  classes?: any[];
}

export default function SeatMapPage({
  params,
  searchParams,
}: {
  params: Promise<{ trainId: string }>
  searchParams: Promise<{
    from?: string
    to?: string
    trainName?: string
    coach?: string
  }>
}) {
  const [trainData, setTrainData] = useState<any>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [resolvedParams, setResolvedParams] = useState<{ trainId: string } | null>(null)
  const [resolvedSearchParams, setResolvedSearchParams] = useState<{
    from?: string
    to?: string
    trainName?: string
    coach?: string
  }>({})

  const router = useRouter()

  useEffect(() => {
    const resolveParams = async () => {
      const p = await params
      const sp = await searchParams
      setResolvedParams(p)
      setResolvedSearchParams(sp)
    }
    resolveParams()
  }, [params, searchParams])

  useEffect(() => {
    if (!resolvedParams) return

    const fetchTrainData = async () => {
      try {
        setLoading(true)
        setError(null)

        console.log("Fetching coaches from API...")

        // Get train details
        const trainDetail = await ApiClient.getTrainDetail(
          resolvedParams.trainId,
          resolvedSearchParams.from,
          resolvedSearchParams.to
        )

        console.log("Coaches API response status:", 200)
        console.log("Coaches API data:", trainDetail)

        setTrainData(trainDetail)
      } catch (err) {
        console.error("Error fetching train data:", err)
        setError(err instanceof Error ? err.message : "Failed to load train data")
      } finally {
        setLoading(false)
      }
    }

    fetchTrainData();
  }, [resolvedParams?.trainId, resolvedSearchParams?.from, resolvedSearchParams?.to]);

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

  if (!trainData) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50 flex items-center justify-center">
        <div className="text-center max-w-md mx-auto p-6">
          <div className="text-gray-600 mb-4">
            <h2 className="text-xl font-semibold">No Train Data Available</h2>
            <p className="text-sm mt-2">Could not load train information.</p>
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

  // Extract coaches from different possible data structures
  const allCoaches: any[] = [];

  // Check classes structure (main structure from API)
  if (trainData.classes && Array.isArray(trainData.classes)) {
    trainData.classes.forEach((trainClass) => {
      if (trainClass.coaches && Array.isArray(trainClass.coaches)) {
        trainClass.coaches.forEach((coach: any) => {
          // Create seat layout from front and back facing seats
          const seatLayout: any[] = [];
          
          // Add front-facing seats
          if (coach.frontFacingSeats && Array.isArray(coach.frontFacingSeats)) {
            coach.frontFacingSeats.forEach((seatNum: number) => {
              seatLayout.push({
                number: seatNum,
                type: 'front_facing',
                color: 'green'
              });
            });
          }
          
          // Add back-facing seats
          if (coach.backFacingSeats && Array.isArray(coach.backFacingSeats)) {
            coach.backFacingSeats.forEach((seatNum: number) => {
              seatLayout.push({
                number: seatNum,
                type: 'back_facing',
                color: 'orange'
              });
            });
          }

          allCoaches.push({
            coach_code: coach.coach_code || coach.code,
            type: trainClass.class_short || trainClass.shortCode || 'UNKNOWN',
            class_name: trainClass.class_name || trainClass.name || 'Unknown Class',
            total_seats: coach.total_seats || coach.totalSeats || 50,
            seat_layout: seatLayout,
            direction: coach.direction || (coach.directionFlipped ? 'reverse' : 'forward'),
            route_code: coach.route_code || trainData.code_from_to,
          });
        });
      }
    });
  }

  // Check train_classes (alternative structure)
  if (allCoaches.length === 0 && trainData.train_classes && Array.isArray(trainData.train_classes)) {
    trainData.train_classes.forEach((trainClass) => {
      if (trainClass.coaches && Array.isArray(trainClass.coaches)) {
        trainClass.coaches.forEach((coach: any) => {
          // Create seat layout from front and back facing seats
          const seatLayout: any[] = [];
          
          // Add front-facing seats
          if (coach.frontFacingSeats && Array.isArray(coach.frontFacingSeats)) {
            coach.frontFacingSeats.forEach((seatNum: number) => {
              seatLayout.push({
                number: seatNum,
                type: 'front_facing',
                color: 'green'
              });
            });
          }
          
          // Add back-facing seats
          if (coach.backFacingSeats && Array.isArray(coach.backFacingSeats)) {
            coach.backFacingSeats.forEach((seatNum: number) => {
              seatLayout.push({
                number: seatNum,
                type: 'back_facing',
                color: 'orange'
              });
            });
          }

          allCoaches.push({
            coach_code: coach.coach_code || coach.code,
            type: trainClass.class_short || trainClass.shortCode || 'UNKNOWN',
            class_name: trainClass.class_name || trainClass.name || 'Unknown Class',
            total_seats: coach.total_seats || coach.totalSeats || 50,
            seat_layout: seatLayout,
            direction: coach.direction || 'forward',
            route_code: coach.route_code || trainData.code_from_to,
          });
        });
      }
    });
  }

  // Handle the case where coaches might be directly on trainData
  if (allCoaches.length === 0 && trainData.coaches && Array.isArray(trainData.coaches)) {
    trainData.coaches.forEach((coach: any) => {
      // Create seat layout from front and back facing seats
      const seatLayout: any[] = [];
      
      // Add front-facing seats
      if (coach.front_facing_seats && Array.isArray(coach.front_facing_seats)) {
        coach.front_facing_seats.forEach((seatNum: number) => {
          seatLayout.push({
            number: seatNum,
            type: 'front_facing',
            color: 'green'
          });
        });
      }
      
      // Add back-facing seats
      if (coach.back_facing_seats && Array.isArray(coach.back_facing_seats)) {
        coach.back_facing_seats.forEach((seatNum: number) => {
          seatLayout.push({
            number: seatNum,
            type: 'back_facing',
            color: 'orange'
          });
        });
      }

      allCoaches.push({
        coach_code: coach.coach_code || coach.code,
        type: coach.type || 'UNKNOWN',
        class_name: coach.class_name || 'Unknown Class',
        total_seats: coach.total_seats || 50,
        seat_layout: seatLayout,
        direction: coach.direction || 'forward',
        route_code: coach.route_code || trainData.code_from_to,
      });
    });
  }

  console.log("Extracted coaches:", allCoaches);

  if (allCoaches.length === 0) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50 flex items-center justify-center">
        <div className="text-center max-w-md mx-auto p-6">
          <div className="text-gray-600 mb-4">
            <h2 className="text-xl font-semibold">No Coach Information Available</h2>
            <p className="text-sm mt-2">No coaches found for this train.</p>
            <details className="mt-4 text-left">
              <summary className="cursor-pointer text-blue-600">Debug Info</summary>
              <pre className="text-xs mt-2 bg-gray-100 p-2 rounded overflow-auto">
                {JSON.stringify(trainData, null, 2)}
              </pre>
            </details>
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

  // Determine which coach to display
  let coachToDisplay: any = null

  if (trainData) {
    console.log("Extracted coaches:", trainData.classes?.[0]?.coaches?.map((c: any) => ({
      coach_code: c.code,
      type: c.code?.split('-')[0] || 'Unknown',
      class_name: trainData.classes?.[0]?.name || 'Unknown',
      total_seats: c.totalSeats || 0,
      seat_layout: [], // Empty for now, will be populated from API data
      direction: c.directionFlipped ? 'reverse' : 'forward'
    })))

    // Try to get coaches from train data
    const coaches = trainData.classes?.[0]?.coaches || []

    if (resolvedSearchParams.coach) {
      // Find specific coach requested
      coachToDisplay = coaches.find((c: any) => c.code === resolvedSearchParams.coach)
      if (coachToDisplay) {
        coachToDisplay = {
          coach_code: coachToDisplay.code,
          type: coachToDisplay.code?.split('-')[0] || 'Unknown',
          class_name: trainData.classes?.[0]?.name || 'Unknown',
          total_seats: coachToDisplay.totalSeats || 0,
          seat_layout: [], // Empty for now
          direction: coachToDisplay.directionFlipped ? 'reverse' : 'forward'
        }
      }
    }

    // Fallback to first coach if none specified or not found
    if (!coachToDisplay && coaches.length > 0) {
      const firstCoach = coaches[0]
      coachToDisplay = {
        coach_code: firstCoach.code,
        type: firstCoach.code?.split('-')[0] || 'Unknown',
        class_name: trainData.classes?.[0]?.name || 'Unknown',
        total_seats: firstCoach.totalSeats || 0,
        seat_layout: [],
        direction: firstCoach.directionFlipped ? 'reverse' : 'forward'
      }
    }
  }

  // Filter by coach if specified
  const selectedCoach = resolvedSearchParams.coach ?
    allCoaches.find(coach => coach.coach_code?.toUpperCase() === resolvedSearchParams.coach?.toUpperCase()) :
    allCoaches[0];

  const coachToDisplayFinal = selectedCoach || allCoaches[0];


  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50 p-4 md:p-8">
      <SeatMapVisual
        coach={coachToDisplayFinal}
        trainName={trainData.train_name || trainData.name || resolvedSearchParams.trainName || "Unknown Train"}
        route={{
          from: resolvedSearchParams.from || trainData.from_station || "Unknown",
          to: resolvedSearchParams.to || trainData.to_station || "Unknown",
        }}
      />
    </div>
  );
}