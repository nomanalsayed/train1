import { type NextRequest, NextResponse } from "next/server"
import { API_CONFIG } from "@/lib/config"

export async function GET(request: NextRequest, { params }: { params: Promise<{ trainId: string }> }) {
  try {
    const resolvedParams = await params
    const trainId = resolvedParams.trainId

    console.log(`[Next.js API] Fetching coaches for train ${trainId}`)

    const wpUrl = `${API_CONFIG.RAIL_BASE_URL}/trains/${trainId}/coaches`
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
      
      // If train not found, provide fallback sample coaches
      if (response.status === 404) {
        console.log(`[Next.js API] Train ${trainId} not found in WordPress, providing fallback coaches`)
        const fallbackCoaches = [
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
          },
          {
            id: 4,
            code: "JA",
            name: "JA",
            type: "S_CHAIR",
            totalSeats: 48,
            position: 4,
            frontFacingSeats: [],
            backFacingSeats: Array.from({length: 48}, (_, i) => i + 1),
            frontFacingCount: 0,
            backFacingCount: 48,
          }
        ]
        
        return NextResponse.json({
          coaches: fallbackCoaches,
          trainId,
          trainName: `Train ${trainId}`,
          trainNumber: trainId,
          count: fallbackCoaches.length,
          fallback: true,
        })
      }
      
      return NextResponse.json(
        {
          error: "Failed to fetch train coaches",
          message: `WordPress API returned ${response.status}`,
          details: errorText,
          trainId,
        },
        { status: response.status },
      )
    }

    const data = await response.json()
    console.log(`[Next.js API] Train ${trainId} coaches data:`, data)

    const coaches = data.coaches || []

    const transformedCoaches = coaches.map((coach: any) => ({
      id: coach.id,
      code: coach.code,
      name: coach.code,
      type: coach.type,
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
    })
  } catch (error) {
    console.error("[Next.js API] Error fetching train coaches:", error)
    return NextResponse.json(
      {
        error: "Failed to fetch train coaches",
        message: error instanceof Error ? error.message : "Unknown error",
        trainId: trainId,
      },
      { status: 500 },
    )
  }
}
