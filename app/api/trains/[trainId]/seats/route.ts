
import { type NextRequest, NextResponse } from "next/server"
import { API_CONFIG } from "@/lib/config"

export async function GET(request: NextRequest, { params }: { params: Promise<{ trainId: string }> }) {
  try {
    const resolvedParams = await params
    const trainId = resolvedParams.trainId

    console.log(`[Next.js API] Fetching seats for train ${trainId}`)

    const wpUrl = `${API_CONFIG.RAIL_BASE_URL}/trains/${trainId}/seats`
    console.log(`[Next.js API] WordPress API URL: ${wpUrl}`)

    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort(), 10000)

    const response = await fetch(wpUrl, {
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "User-Agent": "Bangladesh-Railway-App/1.0",
      },
      signal: controller.signal,
    })

    clearTimeout(timeoutId)

    if (!response.ok) {
      const errorText = await response.text()
      console.error(`[Next.js API] WordPress API error: ${response.status}`, errorText)
      
      // If train not found, provide fallback sample seats
      if (response.status === 404) {
        console.log(`[Next.js API] Train ${trainId} not found in WordPress, providing fallback seats`)
        const fallbackSeats = [
          {
            id: 1,
            code: "CHA",
            name: "CHA",
            type: "S_CHAIR",
            totalSeats: 60,
            position: 1,
            frontFacingSeats: Array.from({length: 30}, (_, i) => i + 1),
            backFacingSeats: Array.from({length: 30}, (_, i) => i + 31),
            frontFacingCount: 30,
            backFacingCount: 30,
          },
          {
            id: 2,
            code: "SCHA",
            name: "SCHA", 
            type: "S_CHAIR",
            totalSeats: 60,
            position: 2,
            frontFacingSeats: Array.from({length: 30}, (_, i) => i + 31),
            backFacingSeats: Array.from({length: 30}, (_, i) => i + 1),
            frontFacingCount: 30,
            backFacingCount: 30,
          },
          {
            id: 3,
            code: "UMA",
            name: "UMA",
            type: "S_CHAIR", 
            totalSeats: 48,
            position: 3,
            frontFacingSeats: Array.from({length: 48}, (_, i) => i + 1),
            backFacingSeats: [],
            frontFacingCount: 48,
            backFacingCount: 0,
          }
        ]
        
        return NextResponse.json({
          coaches: fallbackSeats,
          trainId,
          trainName: `Train ${trainId}`,
          trainNumber: trainId,
          count: fallbackSeats.length,
          fallback: true,
        })
      }
      
      return NextResponse.json(
        {
          error: "Failed to fetch train seats",
          message: `WordPress API returned ${response.status}`,
          details: errorText,
          trainId,
        },
        { status: response.status },
      )
    }

    const data = await response.json()
    console.log(`[Next.js API] Train ${trainId} seats data:`, data)

    const coaches = data.coaches || []

    const transformedCoaches = coaches.map((coach: any) => ({
      id: coach.coach_id || coach.id,
      code: coach.coach_code || coach.code,
      name: coach.coach_code || coach.code,
      type: coach.class_name || coach.type || 'S_CHAIR',
      totalSeats: coach.total_seats || 0,
      position: coach.position || 0,
      frontFacingSeats: coach.front_facing_seats || [],
      backFacingSeats: coach.back_facing_seats || [],
      frontFacingCount: coach.front_facing_seats?.length || 0,
      backFacingCount: coach.back_facing_seats?.length || 0,
    }))

    return NextResponse.json({
      coaches: transformedCoaches,
      trainId,
      trainName: data.train_name,
      trainNumber: data.train_number,
      count: transformedCoaches.length,
      fallback: false,
    })
  } catch (error) {
    console.error("[Next.js API] Error fetching train seats:", error)
    return NextResponse.json(
      {
        error: "Failed to fetch train seats",
        message: error instanceof Error ? error.message : "Unknown error",
        trainId: trainId,
      },
      { status: 500 },
    )
  }
}
