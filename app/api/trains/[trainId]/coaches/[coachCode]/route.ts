import { type NextRequest, NextResponse } from "next/server"
import { API_CONFIG } from "@/lib/config"

export async function GET(request: NextRequest, { params }: { params: { trainId: string; coachCode: string } }) {
  try {
    const { searchParams } = new URL(request.url)
    const from = searchParams.get("from")
    const to = searchParams.get("to")

    const trainNumber = params.trainId
    console.log(`[Next.js API] Fetching coach ${params.coachCode} for train number ${trainNumber}`)

    const wpUrl = new URL(`${API_CONFIG.RAIL_BASE_URL}/trains/${trainNumber}/coaches/${params.coachCode}`)
    if (from) wpUrl.searchParams.set("from", from)
    if (to) wpUrl.searchParams.set("to", to)

    console.log(`[Next.js API] WordPress API URL: ${wpUrl.toString()}`)

    const response = await fetch(wpUrl.toString(), {
      headers: {
        Accept: "application/json",
        "User-Agent": "BD-Railway-App/1.0",
      },
      next: { revalidate: 300 }, // Cache for 5 minutes
    })

    if (!response.ok) {
      const errorText = await response.text()
      console.error(`[Next.js API] WordPress API error: ${response.status}`, errorText)
      return NextResponse.json(
        {
          error: "Failed to fetch coach data",
          message: `WordPress API returned ${response.status}`,
          details: errorText,
          trainNumber: trainNumber,
          coachCode: params.coachCode,
        },
        { status: response.status },
      )
    }

    const coach = await response.json()
    console.log(`[Next.js API] Coach data received:`, coach)

    const transformedData = {
      id: coach.id,
      code: coach.code,
      type: coach.type,
      totalSeats: coach.total_seats || 0,
      frontFacingSeats: coach.front_facing_seats || [],
      backFacingSeats: coach.back_facing_seats || [],
      frontFacingCount: coach.front_facing_count || 0,
      backFacingCount: coach.back_facing_count || 0,
      trainNumber: coach.train_number || trainNumber,
      trainName: coach.train_name,
      direction: coach.direction || "forward",
      isReverseDirection: coach.is_reverse_direction || false,
      directionFlipped: coach.direction_flipped || false,
      directionInfo: coach.direction_info,
      position: coach.position || 0,
    }

    return NextResponse.json(transformedData)
  } catch (error) {
    console.error("[Next.js API] Error fetching coach data:", error)
    return NextResponse.json(
      {
        error: "Failed to fetch coach data",
        message: error instanceof Error ? error.message : "Unknown error",
        trainNumber: params.trainId,
        coachCode: params.coachCode,
      },
      { status: 500 },
    )
  }
}
