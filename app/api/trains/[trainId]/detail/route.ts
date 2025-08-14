import { type NextRequest, NextResponse } from "next/server"
import { API_CONFIG } from "@/lib/config"

const WORDPRESS_API_URL = API_CONFIG.RAIL_BASE_URL

export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ trainId: string }> },
) {
  try {
    const { searchParams } = new URL(request.url)
    const from = searchParams.get("from")
    const to = searchParams.get("to")

    const resolvedParams = await params
    const trainNumber = resolvedParams.trainId

    if (!trainNumber || trainNumber === "undefined") {
      return NextResponse.json({ error: "Train number is required" }, { status: 400 })
    }

    const url = new URL(`${WORDPRESS_API_URL}/trains/${trainNumber}`)
    if (from) url.searchParams.set("from", from)
    if (to) url.searchParams.set("to", to)

    console.log(`[Next.js API] Fetching train by number from: ${url.toString()}`)

    const response = await fetch(url.toString(), {
      headers: {
        "Content-Type": "application/json",
      },
      signal: AbortSignal.timeout(10000),
    })

    if (!response.ok) {
      console.error(`[Next.js API] WordPress API error: ${response.status} ${response.statusText}`)

      if (response.status === 404) {
        return NextResponse.json(
          {
            error: "Train not found",
            message: `Train number ${trainNumber} does not exist in the database`,
            wordpress_url: url.toString(),
          },
          { status: 404 },
        )
      }

      return NextResponse.json(
        {
          error: "WordPress API error",
          message: `Failed to fetch train data: ${response.status} ${response.statusText}`,
          wordpress_url: url.toString(),
        },
        { status: response.status },
      )
    }

    const data = await response.json()
    console.log(`[Next.js API] Successfully fetched train ${trainNumber}`)

    let coaches = data.coaches || []

    if (!coaches || coaches.length === 0) {
      try {
        console.log(
          `[Next.js API] Fetching train-specific coach data from: ${WORDPRESS_API_URL}/trains/${trainNumber}/coaches`,
        )
        const coachResponse = await fetch(`${WORDPRESS_API_URL}/trains/${trainNumber}/coaches`, {
          headers: {
            "Content-Type": "application/json",
          },
          signal: AbortSignal.timeout(5000),
        })

        if (coachResponse.ok) {
          const coachData = await coachResponse.json()
          coaches = coachData.coaches || []
          console.log(`[Next.js API] Fetched ${coaches.length} train-specific coaches with seat data`)
        } else {
          console.warn(`[Next.js API] Train-specific coaches not found, status: ${coachResponse.status}`)
        }
      } catch (coachError) {
        console.warn(`[Next.js API] Failed to fetch train-specific coach data:`, coachError)
      }
    }

    const transformedData = {
      id: data.id || trainNumber,
      name: data.name || `Train ${trainNumber}`,
      train_number: data.train_number || trainNumber,
      primary_number: data.primary_number,
      reverse_number: data.reverse_number,
      is_reverse_direction: data.is_reverse_direction || false,
      direction_info: data.direction_info,
      codeFromTo: `${data.from_station?.code || ""}-${data.to_station?.code || ""}`,
      fromStation: data.from_station
        ? {
            title: data.from_station.title,
            code: data.from_station.code || "",
          }
        : null,
      toStation: data.to_station
        ? {
            title: data.to_station.title,
            code: data.to_station.code || "",
          }
        : null,
      classes:
        coaches && coaches.length > 0
          ? [
              {
                id: 1,
                name: "Chair",
                shortCode: "CHA",
                coaches: coaches.map((coach: any, index: number) => ({
                  id: coach.id || index + 1,
                  code: coach.code || `CHA-${index + 1}`,
                  totalSeats: coach.total_seats || 0,
                  frontFacingSeats: coach.front_facing_seats || [],
                  backFacingSeats: coach.back_facing_seats || [],
                  directionFlipped: coach.direction_flipped || false,
                })),
              },
            ]
          : [],
      _meta: {
        source: "wordpress",
        wordpress_url: url.toString(),
        coaches_count: coaches.length,
        uses_train_number: true,
      },
    }

    return NextResponse.json(transformedData)
  } catch (error) {
    console.error("[Next.js API] Train detail error:", error)

    return NextResponse.json(
      {
        error: "Connection failed",
        message:
          "Unable to connect to WordPress API. Please check your WordPress installation and plugin configuration.",
        details: error instanceof Error ? error.message : String(error),
        wordpress_url: WORDPRESS_API_URL,
      },
      { status: 500 },
    )
  }
}
